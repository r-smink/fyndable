<?php

namespace SSEOAIClient;

/**
 * Google Ads API Client
 * 
 * Uses the Google Ads API REST interface to fetch campaign and performance data.
 * Relies on GscOAuth (unified Google OAuth) for token management.
 * 
 * @see https://developers.google.com/google-ads/api/docs/start
 */
class GoogleAdsClient
{
    private Settings $settings;
    private GscOAuth $oauth;
    private DashboardAPI $dashboardAPI;

    private const API_BASE = 'https://googleads.googleapis.com/v17/';

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->oauth = new GscOAuth($settings);
        $this->dashboardAPI = new DashboardAPI($settings);
    }

    /**
     * Check if Google Ads is connected (has valid tokens and customer ID)
     */
    public function isConnected(): bool
    {
        $tokens = get_option('aiseoclient_gsc_tokens', []);
        $customerId = get_option('sseo_ai_google_ads_customer_id', '');
        return !empty($tokens['access_token']) && !empty($customerId);
    }

    /**
     * Get the Google Ads customer ID (10-digit, dashes optional)
     */
    private function getCustomerId(): string
    {
        $id = get_option('sseo_ai_google_ads_customer_id', '');
        return str_replace('-', '', $id);
    }

    /**
     * Get developer token (central, from GscOAuth)
     */
    private function getDevToken(): string
    {
        return $this->oauth->getAdsDevToken();
    }

    /**
     * Query the Google Ads API via GAQL (Google Ads Query Language).
     * 
     * @param string $query GAQL query string
     * @return array|\WP_Error
     */
    public function query(string $query): array|\WP_Error
    {
        if (!$this->isConnected()) {
            return new \WP_Error(
                'ads_not_connected',
                __('Google Ads is not connected. Please connect via AI SEO → Integrations and set your Customer ID.', 'ai-seo-client')
            );
        }

        $accessToken = $this->oauth->getAccessToken();
        if (empty($accessToken)) {
            return new \WP_Error('ads_auth', __('Unable to get access token. Please reconnect your Google account.', 'ai-seo-client'));
        }

        $customerId = $this->getCustomerId();
        $devToken = $this->getDevToken();
        $apiUrl = self::API_BASE . "customers/{$customerId}/googleAds:searchStream";

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ];

        if ($devToken) {
            $headers['developer-token'] = $devToken;
        }

        $resp = wp_remote_post($apiUrl, [
            'timeout' => 30,
            'headers' => $headers,
            'body' => wp_json_encode(['query' => $query]),
        ]);

        if (is_wp_error($resp)) {
            return $resp;
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);

        if ($code === 401) {
            $refreshed = $this->oauth->refresh();
            if (!is_wp_error($refreshed)) {
                return $this->query($query);
            }
            return new \WP_Error('ads_auth_expired', __('Google authentication expired. Please reconnect.', 'ai-seo-client'));
        }

        if ($code !== 200) {
            $errorMsg = $body['error']['message'] ?? __('Google Ads API error', 'ai-seo-client');
            return new \WP_Error('ads_api', $errorMsg, ['code' => $code]);
        }

        $this->dashboardAPI->reportGoogleUsage('ads');

        return $body;
    }

    /**
     * Get campaign performance overview.
     * 
     * @param int $days Number of days to look back
     * @return array|\WP_Error
     */
    public function getCampaignOverview(int $days = 30): array|\WP_Error
    {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $gaql = "SELECT campaign.id, campaign.name, campaign.status, metrics.clicks, metrics.impressions, metrics.cost_micros, metrics.ctr, metrics.conversions, metrics.cost_per_conversion FROM campaign WHERE segments.date BETWEEN '{$startDate}' AND '{$endDate}'";

        $result = $this->query($gaql);

        if (is_wp_error($result)) {
            return $result;
        }

        $campaigns = [];
        $totalClicks = 0;
        $totalImpressions = 0;
        $totalCost = 0;
        $totalConversions = 0;

        if (!empty($result[0]['results'])) {
            foreach ($result[0]['results'] as $row) {
                $clicks = (int)($row['metrics']['clicks'] ?? 0);
                $impressions = (int)($row['metrics']['impressions'] ?? 0);
                $costMicros = (int)($row['metrics']['costMicros'] ?? 0);
                $cost = $costMicros / 1000000;
                $conversions = (float)($row['metrics']['conversions'] ?? 0);

                $campaigns[] = [
                    'id' => $row['campaign']['id'] ?? '',
                    'name' => $row['campaign']['name'] ?? '',
                    'status' => $row['campaign']['status'] ?? '',
                    'clicks' => $clicks,
                    'impressions' => $impressions,
                    'cost' => round($cost, 2),
                    'ctr' => round((float)($row['metrics']['ctr'] ?? 0) * 100, 2),
                    'conversions' => $conversions,
                    'cost_per_conversion' => round((float)($row['metrics']['costPerConversion'] ?? 0) / 1000000, 2),
                ];

                $totalClicks += $clicks;
                $totalImpressions += $impressions;
                $totalCost += $cost;
                $totalConversions += $conversions;
            }
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_clicks' => $totalClicks,
            'total_impressions' => $totalImpressions,
            'total_cost' => round($totalCost, 2),
            'total_conversions' => $totalConversions,
            'avg_ctr' => $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0,
            'cost_per_conversion' => $totalConversions > 0 ? round($totalCost / $totalConversions, 2) : 0,
            'campaigns' => $campaigns,
        ];
    }

    /**
     * Get daily performance breakdown.
     * 
     * @param int $days
     * @return array|\WP_Error
     */
    public function getDailyPerformance(int $days = 30): array|\WP_Error
    {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $gaql = "SELECT segments.date, metrics.clicks, metrics.impressions, metrics.cost_micros, metrics.conversions FROM campaign WHERE segments.date BETWEEN '{$startDate}' AND '{$endDate}' ORDER BY segments.date ASC";

        $result = $this->query($gaql);

        if (is_wp_error($result)) {
            return $result;
        }

        $daily = [];
        $byDate = [];

        if (!empty($result[0]['results'])) {
            foreach ($result[0]['results'] as $row) {
                $date = $row['segments']['date'] ?? '';
                if (!isset($byDate[$date])) {
                    $byDate[$date] = [
                        'date' => $date,
                        'clicks' => 0,
                        'impressions' => 0,
                        'cost' => 0,
                        'conversions' => 0,
                    ];
                }
                $byDate[$date]['clicks'] += (int)($row['metrics']['clicks'] ?? 0);
                $byDate[$date]['impressions'] += (int)($row['metrics']['impressions'] ?? 0);
                $byDate[$date]['cost'] += (int)($row['metrics']['costMicros'] ?? 0) / 1000000;
                $byDate[$date]['conversions'] += (float)($row['metrics']['conversions'] ?? 0);
            }
        }

        foreach ($byDate as $entry) {
            $entry['cost'] = round($entry['cost'], 2);
            $daily[] = $entry;
        }

        usort($daily, fn($a, $b) => strcmp($a['date'], $b['date']));

        return ['daily' => $daily];
    }

    /**
     * Get keyword performance from Google Ads.
     * 
     * @param int $days
     * @param int $limit
     * @return array|\WP_Error
     */
    public function getKeywordPerformance(int $days = 30, int $limit = 20): array|\WP_Error
    {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $gaql = "SELECT ad_group_criterion.keyword.text, ad_group_criterion.keyword.match_type, metrics.clicks, metrics.impressions, metrics.cost_micros, metrics.ctr, metrics.conversions, metrics.average_cpc FROM ad_group_criterion WHERE ad_group_criterion.type = 'KEYWORD' AND segments.date BETWEEN '{$startDate}' AND '{$endDate}' ORDER BY metrics.clicks DESC LIMIT {$limit}";

        $result = $this->query($gaql);

        if (is_wp_error($result)) {
            return $result;
        }

        $keywords = [];
        if (!empty($result[0]['results'])) {
            foreach ($result[0]['results'] as $row) {
                $keywords[] = [
                    'keyword' => $row['adGroupCriterion']['keyword']['text'] ?? '',
                    'match_type' => $row['adGroupCriterion']['keyword']['matchType'] ?? '',
                    'clicks' => (int)($row['metrics']['clicks'] ?? 0),
                    'impressions' => (int)($row['metrics']['impressions'] ?? 0),
                    'cost' => round((int)($row['metrics']['costMicros'] ?? 0) / 1000000, 2),
                    'ctr' => round((float)($row['metrics']['ctr'] ?? 0) * 100, 2),
                    'conversions' => (float)($row['metrics']['conversions'] ?? 0),
                    'avg_cpc' => round((int)($row['metrics']['averageCpc'] ?? 0) / 1000000, 2),
                ];
            }
        }

        return ['keywords' => $keywords];
    }

    /**
     * List accessible customers (accounts).
     * 
     * @return array|\WP_Error
     */
    public function listCustomers(): array|\WP_Error
    {
        $accessToken = $this->oauth->getAccessToken();
        if (empty($accessToken)) {
            return new \WP_Error('ads_auth', __('Unable to get access token.', 'ai-seo-client'));
        }

        $devToken = $this->getDevToken();

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
        ];

        if ($devToken) {
            $headers['developer-token'] = $devToken;
        }

        $resp = wp_remote_get(self::API_BASE . 'customers:listAccessibleCustomers', [
            'timeout' => 30,
            'headers' => $headers,
        ]);

        if (is_wp_error($resp)) {
            return $resp;
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);

        if ($code !== 200) {
            return new \WP_Error('ads_list', $body['error']['message'] ?? __('Failed to list customers', 'ai-seo-client'));
        }

        $customers = [];
        if (!empty($body['resourceNames'])) {
            foreach ($body['resourceNames'] as $name) {
                $customers[] = str_replace('customers/', '', $name);
            }
        }

        return ['customers' => $customers];
    }
}

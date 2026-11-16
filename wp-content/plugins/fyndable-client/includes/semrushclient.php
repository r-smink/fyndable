<?php

namespace SSEOAIClient;

/**
 * Semrush Analytics API client (customer's own key, "bring your own key").
 *
 * Docs: https://developer.semrush.com/api/v3/seo/
 * - Domain/keyword reports: https://api.semrush.com/?type=...
 * - Backlinks:              https://api.semrush.com/analytics/v1/?type=...
 *
 * Responses are `;`-separated CSV with a header row; errors come back as a
 * single line "ERROR <code> :: <message>". Every data line costs API units,
 * so all calls are cached and counted against an optional monthly budget.
 */
class SemrushClient
{
    public const KEY_OPTION      = 'sseo_ai_semrush_api_key';
    public const DATABASE_OPTION = 'sseo_ai_semrush_database';
    public const BUDGET_OPTION   = 'sseo_ai_semrush_unit_budget';
    public const USAGE_OPTION    = 'sseo_ai_semrush_units_used';

    private const API_BASE       = 'https://api.semrush.com/';
    private const BACKLINKS_BASE = 'https://api.semrush.com/analytics/v1/';
    private const BALANCE_URL    = 'https://www.semrush.com/users/countapiunits.html';

    private const CACHE_TTL       = DAY_IN_SECONDS;
    private const EMPTY_CACHE_TTL = 15 * MINUTE_IN_SECONDS;

    /** API units per returned line (per request for *_overview). */
    private const UNIT_PRICES = [
        'domain_rank'            => 10,
        'domain_rank_history'    => 10,
        'domain_organic'         => 10,
        'domain_organic_unique'  => 10,
        'domain_organic_organic' => 40,
        'domain_domains'         => 80,
        'phrase_this'            => 10,
        'phrase_these'           => 10,
        'phrase_related'         => 40,
        'phrase_questions'       => 40,
        'phrase_kdi'             => 50,
        'backlinks'              => 40,
        'backlinks_refdomains'   => 40,
        'backlinks_anchors'      => 40,
    ];
    private const PER_REQUEST_PRICES = [
        'backlinks_overview' => 40,
    ];

    /** Semrush regional databases offered in the settings (code => label). */
    public const DATABASES = [
        'nl' => 'Netherlands', 'be' => 'Belgium', 'de' => 'Germany', 'fr' => 'France',
        'uk' => 'United Kingdom', 'us' => 'United States', 'es' => 'Spain', 'it' => 'Italy',
        'at' => 'Austria', 'ch' => 'Switzerland', 'dk' => 'Denmark', 'se' => 'Sweden',
        'no' => 'Norway', 'pl' => 'Poland', 'pt' => 'Portugal', 'ie' => 'Ireland',
        'ca' => 'Canada', 'au' => 'Australia', 'br' => 'Brazil',
    ];

    private string $apiKey;
    private string $database;

    public function __construct(string $apiKey, string $database = '')
    {
        $this->apiKey = trim($apiKey);
        $this->database = $database !== '' ? $database : self::defaultDatabase();
    }

    /**
     * Client built from the saved settings (key is stored encrypted).
     */
    public static function fromOptions(): self
    {
        $database = (string) get_option(self::DATABASE_OPTION, '');
        if (!isset(self::DATABASES[$database])) {
            $database = '';
        }
        return new self(self::decryptKey((string) get_option(self::KEY_OPTION, '')), $database);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    /**
     * Database from the site locale (nl_NL → nl, en_GB → uk, ...), fallback us.
     */
    public static function defaultDatabase(): string
    {
        $locale = strtolower((string) get_locale());
        $map = [
            'nl_nl' => 'nl', 'nl_be' => 'be', 'fr_be' => 'be', 'de_de' => 'de', 'de_at' => 'at',
            'de_ch' => 'ch', 'fr_fr' => 'fr', 'es_es' => 'es', 'it_it' => 'it', 'en_gb' => 'uk',
            'en_au' => 'au', 'en_ca' => 'ca', 'pt_br' => 'br', 'pt_pt' => 'pt', 'da_dk' => 'dk',
            'sv_se' => 'se', 'nb_no' => 'no', 'pl_pl' => 'pl', 'en_ie' => 'ie',
        ];
        return $map[$locale] ?? 'us';
    }

    // ---------------------------------------------------------------------
    // Key storage (AES-256-GCM, same scheme as the Google refresh token)
    // ---------------------------------------------------------------------

    private static function cryptKey(): string
    {
        return hash('sha256', wp_salt('auth') . wp_salt('secure_auth'), true);
    }

    /**
     * Sanitize callback for the key option: encrypts new keys, passes an
     * already-encrypted (unchanged) value through, empty removes the key.
     */
    public static function sanitizeKeyOption($value): string
    {
        $value = trim(sanitize_text_field((string) $value));
        if ($value === '' || strpos($value, 'enc1:') === 0 || !function_exists('openssl_encrypt')) {
            return $value;
        }
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($value, 'aes-256-gcm', self::cryptKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            return $value;
        }
        return 'enc1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decryptKey(string $value): string
    {
        if (strpos($value, 'enc1:') !== 0) {
            return $value;
        }
        $raw = base64_decode(substr($value, 5), true);
        if ($raw === false || strlen($raw) < 29 || !function_exists('openssl_decrypt')) {
            return '';
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::cryptKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? '' : $plain;
    }

    // ---------------------------------------------------------------------
    // Unit budget
    // ---------------------------------------------------------------------

    /**
     * Estimated API units used this calendar month (by this site).
     */
    public static function getUnitsUsedThisMonth(): int
    {
        $usage = get_option(self::USAGE_OPTION, []);
        if (!is_array($usage) || ($usage['month'] ?? '') !== gmdate('Y-m')) {
            return 0;
        }
        return (int) ($usage['units'] ?? 0);
    }

    private static function addUnitsUsed(int $units): void
    {
        if ($units <= 0) {
            return;
        }
        update_option(self::USAGE_OPTION, [
            'month' => gmdate('Y-m'),
            'units' => self::getUnitsUsedThisMonth() + $units,
        ], false);
    }

    private static function getBudget(): int
    {
        return max(0, (int) get_option(self::BUDGET_OPTION, 0));
    }

    private function estimateUnits(string $type, int $lines): int
    {
        if (isset(self::PER_REQUEST_PRICES[$type])) {
            return self::PER_REQUEST_PRICES[$type];
        }
        return (self::UNIT_PRICES[$type] ?? 10) * max(1, $lines);
    }

    // ---------------------------------------------------------------------
    // Transport
    // ---------------------------------------------------------------------

    /**
     * Run a report and return its rows as associative arrays (header => value).
     *
     * @return array<int, array<string, string>>|\WP_Error
     */
    private function report(string $type, array $params, bool $backlinks = false): array|\WP_Error
    {
        if (!$this->isConfigured()) {
            return new \WP_Error('semrush_not_configured', __('Semrush API key not configured.', 'ai-seo-client'));
        }

        if (!$backlinks && !isset($params['database'])) {
            $params['database'] = $this->database;
        }
        $params = array_merge(['type' => $type], $params);

        $cacheKey = 'sseo_semr_' . md5(md5($this->apiKey) . wp_json_encode($params));
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $limit = (int) ($params['display_limit'] ?? 1);
        $budget = self::getBudget();
        if ($budget > 0 && self::getUnitsUsedThisMonth() + $this->estimateUnits($type, $limit) > $budget) {
            return new \WP_Error(
                'semrush_budget',
                __('The monthly Semrush API unit budget set in Integrations is reached. Raise the budget or wait until next month.', 'ai-seo-client')
            );
        }

        $url = ($backlinks ? self::BACKLINKS_BASE : self::API_BASE) . '?' . http_build_query(
            array_merge($params, ['key' => $this->apiKey]),
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        $response = wp_remote_get($url, ['timeout' => 30, 'redirection' => 0]);
        if (is_wp_error($response)) {
            return $response;
        }

        $body = trim((string) wp_remote_retrieve_body($response));
        $status = (int) wp_remote_retrieve_response_code($response);

        if (preg_match('/^ERROR\s+(\d+)\s*::\s*(.*)$/s', $body, $m)) {
            $error = $this->mapError((int) $m[1], trim($m[2]));
            if ($error->get_error_code() === 'semrush_no_data') {
                // "Nothing found" is a valid, empty answer; cache it briefly.
                set_transient($cacheKey, [], self::EMPTY_CACHE_TTL);
                return [];
            }
            return $error;
        }

        if ($status < 200 || $status >= 300) {
            return new \WP_Error('semrush_http', sprintf(__('Semrush API error (HTTP %d).', 'ai-seo-client'), $status));
        }

        $rows = self::parseCsv($body);
        self::addUnitsUsed($this->estimateUnits($type, count($rows)));
        set_transient($cacheKey, $rows, $rows ? self::CACHE_TTL : self::EMPTY_CACHE_TTL);

        return $rows;
    }

    private function mapError(int $code, string $message): \WP_Error
    {
        switch ($code) {
            case 50:
                return new \WP_Error('semrush_no_data', __('Semrush has no data for this request.', 'ai-seo-client'));
            case 120:
            case 121:
            case 131:
                return new \WP_Error('semrush_auth', __('The Semrush API key is invalid.', 'ai-seo-client'));
            case 130:
            case 133:
                return new \WP_Error('semrush_no_access', __('This Semrush account has no API access (a Business plan with API units is required).', 'ai-seo-client'));
            case 132:
                return new \WP_Error('semrush_units', __('The Semrush API unit balance is zero. Buy API units in your Semrush account.', 'ai-seo-client'));
            case 134:
                return new \WP_Error('semrush_limit', __('The Semrush API limit for this key is exceeded.', 'ai-seo-client'));
            default:
                return new \WP_Error('semrush_error', sprintf(__('Semrush API error %1$d: %2$s', 'ai-seo-client'), $code, $message));
        }
    }

    /**
     * Parse Semrush CSV (`;` separated, header row) into associative rows.
     */
    public static function parseCsv(string $body): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($body));
        if (!$lines || count($lines) < 2) {
            return [];
        }

        $header = array_map('trim', str_getcsv(array_shift($lines), ';', '"', ''));
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line, ';', '"', '');
            if (count($values) < count($header)) {
                $values = array_pad($values, count($header), '');
            }
            $rows[] = array_combine($header, array_slice($values, 0, count($header)));
        }
        return $rows;
    }

    private static function num($value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Semrush "Td" trend: comma-separated monthly ratios.
     */
    private static function trend($value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }
        return array_map('floatval', explode(',', $value));
    }

    // ---------------------------------------------------------------------
    // Account
    // ---------------------------------------------------------------------

    /**
     * Remaining API units on the account (free call, not cached).
     */
    public function getApiUnitsBalance(): int|\WP_Error
    {
        if (!$this->isConfigured()) {
            return new \WP_Error('semrush_not_configured', __('Semrush API key not configured.', 'ai-seo-client'));
        }
        $response = wp_remote_get(self::BALANCE_URL . '?key=' . rawurlencode($this->apiKey), ['timeout' => 15, 'redirection' => 0]);
        if (is_wp_error($response)) {
            return $response;
        }
        $body = trim((string) wp_remote_retrieve_body($response));
        if (preg_match('/^ERROR\s+(\d+)\s*::\s*(.*)$/s', $body, $m)) {
            return $this->mapError((int) $m[1], trim($m[2]));
        }
        if (!is_numeric($body)) {
            return new \WP_Error('semrush_auth', __('The Semrush API key is invalid.', 'ai-seo-client'));
        }
        return (int) $body;
    }

    // ---------------------------------------------------------------------
    // Domain reports
    // ---------------------------------------------------------------------

    /**
     * Organic + paid overview for one database.
     *
     * @return array{rank:int, organic_keywords:int, organic_traffic:int, organic_cost:float, paid_keywords:int, paid_traffic:int}|\WP_Error
     */
    public function getDomainOverview(string $domain): array|\WP_Error
    {
        $rows = $this->report('domain_rank', [
            'domain'         => $domain,
            'export_columns' => 'Dn,Rk,Or,Ot,Oc,Ad,At',
        ]);
        if (is_wp_error($rows)) {
            return $rows;
        }
        $row = $rows[0] ?? [];

        return [
            'rank'             => (int) self::num($row['Rank'] ?? 0),
            'organic_keywords' => (int) self::num($row['Organic Keywords'] ?? 0),
            'organic_traffic'  => (int) self::num($row['Organic Traffic'] ?? 0),
            'organic_cost'     => self::num($row['Organic Cost'] ?? 0),
            'paid_keywords'    => (int) self::num($row['Adwords Keywords'] ?? 0),
            'paid_traffic'     => (int) self::num($row['Adwords Traffic'] ?? 0),
        ];
    }

    /**
     * Monthly organic history (newest first).
     */
    public function getDomainHistory(string $domain, int $months = 12): array|\WP_Error
    {
        $rows = $this->report('domain_rank_history', [
            'domain'         => $domain,
            'display_limit'  => max(1, min(24, $months)),
            'export_columns' => 'Dt,Rk,Or,Ot,Oc',
        ]);
        if (is_wp_error($rows)) {
            return $rows;
        }

        return array_map(fn($r) => [
            'date'             => (string) ($r['Date'] ?? ''),
            'organic_keywords' => (int) self::num($r['Organic Keywords'] ?? 0),
            'organic_traffic'  => (int) self::num($r['Organic Traffic'] ?? 0),
            'organic_cost'     => self::num($r['Organic Cost'] ?? 0),
        ], $rows);
    }

    /**
     * Keywords a domain ranks for organically.
     *
     * $args: limit, sort (tr_desc|nq_desc|po_asc), min_position, max_position.
     */
    public function getDomainKeywords(string $domain, array $args = []): array|\WP_Error
    {
        $params = [
            'domain'         => $domain,
            'display_limit'  => max(1, min(500, (int) ($args['limit'] ?? 25))),
            'display_sort'   => $args['sort'] ?? 'tr_desc',
            'export_columns' => 'Ph,Po,Pp,Nq,Cp,Ur,Tr,Co,Kd,Td',
        ];

        $filters = [];
        if (!empty($args['min_position'])) {
            $filters[] = '+|Po|Gt|' . ((int) $args['min_position'] - 1);
        }
        if (!empty($args['max_position'])) {
            $filters[] = '+|Po|Lt|' . ((int) $args['max_position'] + 1);
        }
        if ($filters) {
            $params['display_filter'] = implode('|', $filters);
        }

        $rows = $this->report('domain_organic', $params);
        if (is_wp_error($rows)) {
            return $rows;
        }

        return array_map(fn($r) => [
            'keyword'           => (string) ($r['Keyword'] ?? ''),
            'position'          => (int) self::num($r['Position'] ?? 0),
            'previous_position' => (int) self::num($r['Previous Position'] ?? 0),
            'volume'            => (int) self::num($r['Search Volume'] ?? 0),
            'cpc'               => self::num($r['CPC'] ?? 0),
            'url'               => (string) ($r['Url'] ?? ''),
            'traffic_percent'   => self::num($r['Traffic (%)'] ?? 0),
            'competition'       => self::num($r['Competition'] ?? 0),
            'difficulty'        => (int) self::num($r['Keyword Difficulty'] ?? $r['Keyword Difficulty Index'] ?? 0),
            'trend'             => self::trend($r['Trends'] ?? ''),
        ], $rows);
    }

    /**
     * Top pages by organic traffic.
     */
    public function getTopPages(string $domain, int $limit = 15): array|\WP_Error
    {
        $rows = $this->report('domain_organic_unique', [
            'domain'         => $domain,
            'display_limit'  => max(1, min(100, $limit)),
            'display_sort'   => 'tr_desc',
            'export_columns' => 'Ur,Pc,Tg,Tr',
        ]);
        if (is_wp_error($rows)) {
            return $rows;
        }

        return array_map(fn($r) => [
            'url'             => (string) ($r['Url'] ?? ''),
            'keywords'        => (int) self::num($r['Number of Keywords'] ?? 0),
            'traffic'         => (int) self::num($r['Traffic'] ?? 0),
            'traffic_percent' => self::num($r['Traffic (%)'] ?? 0),
        ], $rows);
    }

    /**
     * Organic competitors.
     */
    public function getCompetitors(string $domain, int $limit = 10): array|\WP_Error
    {
        $rows = $this->report('domain_organic_organic', [
            'domain'         => $domain,
            'display_limit'  => max(1, min(50, $limit)),
            'export_columns' => 'Dn,Cr,Np,Or,Ot,Oc',
        ]);
        if (is_wp_error($rows)) {
            return $rows;
        }

        return array_map(fn($r) => [
            'domain'           => (string) ($r['Domain'] ?? ''),
            'relevance'        => self::num($r['Competitor Relevance'] ?? 0),
            'common_keywords'  => (int) self::num($r['Common Keywords'] ?? 0),
            'organic_keywords' => (int) self::num($r['Organic Keywords'] ?? 0),
            'organic_traffic'  => (int) self::num($r['Organic Traffic'] ?? 0),
            'organic_cost'     => self::num($r['Organic Cost'] ?? 0),
        ], $rows);
    }

    /**
     * Keywords the competitor ranks for and $domain does not ("missing").
     */
    public function getKeywordGap(string $domain, string $competitor, int $limit = 50): array|\WP_Error
    {
        $rows = $this->report('domain_domains', [
            'domains'        => '*|or|' . $competitor . '|-|or|' . $domain,
            'display_limit'  => max(1, min(200, $limit)),
            'display_sort'   => 'nq_desc',
            'export_columns' => 'Ph,P0,Nq,Cp,Co,Kd',
        ]);
        if (is_wp_error($rows)) {
            return $rows;
        }

        return array_map(function ($r) use ($competitor) {
            return [
                'keyword'             => (string) ($r['Keyword'] ?? ''),
                'competitor_position' => (int) self::num($r[$competitor] ?? array_values($r)[1] ?? 0),
                'volume'              => (int) self::num($r['Search Volume'] ?? 0),
                'cpc'                 => self::num($r['CPC'] ?? 0),
                'competition'         => self::num($r['Competition'] ?? 0),
                'difficulty'          => (int) self::num($r['Keyword Difficulty'] ?? $r['Keyword Difficulty Index'] ?? 0),
            ];
        }, $rows);
    }

    // ---------------------------------------------------------------------
    // Keyword reports
    // ---------------------------------------------------------------------

    private static function mapKeywordRow(array $r): array
    {
        return [
            'keyword'     => (string) ($r['Keyword'] ?? ''),
            'volume'      => (int) self::num($r['Search Volume'] ?? 0),
            'cpc'         => self::num($r['CPC'] ?? 0),
            'competition' => self::num($r['Competition'] ?? 0),
            'difficulty'  => (int) self::num($r['Keyword Difficulty'] ?? $r['Keyword Difficulty Index'] ?? 0),
            'results'     => (int) self::num($r['Number of Results'] ?? 0),
            'trend'       => self::trend($r['Trends'] ?? ''),
        ];
    }

    /**
     * Volume, CPC, competition and difficulty for up to 100 keywords.
     *
     * @return array<string, array> keyed by lowercase keyword
     */
    public function getKeywordMetrics(array $keywords): array|\WP_Error
    {
        $keywords = array_values(array_unique(array_filter(array_map(
            fn($k) => trim(str_replace(';', ' ', (string) $k)),
            $keywords
        ))));
        if (!$keywords) {
            return [];
        }

        $result = [];
        foreach (array_chunk($keywords, 100) as $chunk) {
            $rows = $this->report('phrase_these', [
                'phrase'         => implode(';', $chunk),
                'display_limit'  => count($chunk),
                'export_columns' => 'Ph,Nq,Cp,Co,Nr,Kd,Td',
            ]);
            if (is_wp_error($rows)) {
                return $rows;
            }
            foreach ($rows as $r) {
                $row = self::mapKeywordRow($r);
                $result[mb_strtolower($row['keyword'])] = $row;
            }
        }
        return $result;
    }

    public function getRelatedKeywords(string $keyword, int $limit = 25): array|\WP_Error
    {
        $rows = $this->report('phrase_related', [
            'phrase'         => $keyword,
            'display_limit'  => max(1, min(100, $limit)),
            'display_sort'   => 'nq_desc',
            'export_columns' => 'Ph,Nq,Cp,Co,Nr,Kd,Td',
        ]);
        return is_wp_error($rows) ? $rows : array_map([self::class, 'mapKeywordRow'], $rows);
    }

    public function getQuestions(string $keyword, int $limit = 25): array|\WP_Error
    {
        $rows = $this->report('phrase_questions', [
            'phrase'         => $keyword,
            'display_limit'  => max(1, min(100, $limit)),
            'display_sort'   => 'nq_desc',
            'export_columns' => 'Ph,Nq,Cp,Co,Nr,Kd,Td',
        ]);
        return is_wp_error($rows) ? $rows : array_map([self::class, 'mapKeywordRow'], $rows);
    }

    // ---------------------------------------------------------------------
    // Backlinks
    // ---------------------------------------------------------------------

    /**
     * Backlink overview incl. Authority Score.
     */
    public function getBacklinksOverview(string $domain): array|\WP_Error
    {
        $rows = $this->report('backlinks_overview', [
            'target'         => $domain,
            'target_type'    => 'root_domain',
            'export_columns' => 'ascore,total,domains_num,urls_num,ips_num,follows_num,nofollows_num,sponsored_num,ugc_num',
        ], true);
        if (is_wp_error($rows)) {
            return $rows;
        }
        $row = $rows[0] ?? [];

        return [
            'authority_score'   => (int) self::num($row['ascore'] ?? 0),
            'backlinks'         => (int) self::num($row['total'] ?? 0),
            'referring_domains' => (int) self::num($row['domains_num'] ?? 0),
            'referring_urls'    => (int) self::num($row['urls_num'] ?? 0),
            'referring_ips'     => (int) self::num($row['ips_num'] ?? 0),
            'follow'            => (int) self::num($row['follows_num'] ?? 0),
            'nofollow'          => (int) self::num($row['nofollows_num'] ?? 0),
            'sponsored'         => (int) self::num($row['sponsored_num'] ?? 0),
            'ugc'               => (int) self::num($row['ugc_num'] ?? 0),
        ];
    }

    /**
     * Individual backlinks, in the field names BacklinkAnalyzer::normalizeLink() reads.
     */
    public function getBacklinks(string $domain, int $limit = 200): array|\WP_Error
    {
        $rows = $this->report('backlinks', [
            'target'         => $domain,
            'target_type'    => 'root_domain',
            'display_limit'  => max(1, min(1000, $limit)),
            'display_sort'   => 'page_ascore_desc',
            'export_columns' => 'page_ascore,source_url,source_title,target_url,anchor,nofollow,image,first_seen',
        ], true);
        if (is_wp_error($rows)) {
            return $rows;
        }

        return array_map(function ($r) {
            $sourceUrl = (string) ($r['source_url'] ?? '');
            $firstSeen = (int) self::num($r['first_seen'] ?? 0);
            return [
                'source_url'         => $sourceUrl,
                'source_domain'      => (string) (parse_url($sourceUrl, PHP_URL_HOST) ?: ''),
                'target_url'         => (string) ($r['target_url'] ?? ''),
                'anchor'             => (string) ($r['anchor'] ?? ''),
                'nofollow'           => in_array(strtolower((string) ($r['nofollow'] ?? '')), ['true', '1'], true),
                'item_type'          => in_array(strtolower((string) ($r['image'] ?? '')), ['true', '1'], true) ? 'image' : 'anchor',
                'url_rating_source'  => (int) self::num($r['page_ascore'] ?? 0),
                'title'              => (string) ($r['source_title'] ?? ''),
                'first_seen'         => $firstSeen > 0 ? gmdate('Y-m-d', $firstSeen) : '',
            ];
        }, $rows);
    }

    /**
     * Referring domains with their Authority Score.
     */
    public function getReferringDomains(string $domain, int $limit = 100): array|\WP_Error
    {
        $rows = $this->report('backlinks_refdomains', [
            'target'         => $domain,
            'target_type'    => 'root_domain',
            'display_limit'  => max(1, min(1000, $limit)),
            'display_sort'   => 'domain_ascore_desc',
            'export_columns' => 'domain_ascore,domain,backlinks_num,first_seen',
        ], true);
        if (is_wp_error($rows)) {
            return $rows;
        }

        return array_map(fn($r) => [
            'domain'          => (string) ($r['domain'] ?? ''),
            'authority_score' => (int) self::num($r['domain_ascore'] ?? 0),
            'backlinks'       => (int) self::num($r['backlinks_num'] ?? 0),
        ], $rows);
    }
}

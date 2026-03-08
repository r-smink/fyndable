<?php

namespace AISEOAssistant;

class LicenseManager
{
    private const LICENSE_OPTION = 'aiseo_license';
    private const LICENSE_TRANSIENT = 'aiseo_license_check';
    private const TRIAL_DURATION_DAYS = 14;
    
    private string $licenseServerUrl;
    private string $productId;
    
    public function __construct(string $licenseServerUrl = '', string $productId = 'ai-seo-assistant-pro')
    {
        $this->licenseServerUrl = rtrim($licenseServerUrl, '/');
        $this->productId = $productId;
    }
    
    /**
     * Get license data
     */
    public function getLicense(): array
    {
        $license = get_option(self::LICENSE_OPTION, []);
        return wp_parse_args($license, [
            'key' => '',
            'status' => 'inactive',
            'tier' => 'free',
            'expires' => '',
            'activated_at' => '',
            'last_check' => '',
            'is_trial' => false,
            'trial_start' => '',
            'features' => [],
            'site_count' => 0,
            'max_sites' => 1,
        ]);
    }
    
    /**
     * Save license data
     */
    public function saveLicense(array $data): void
    {
        update_option(self::LICENSE_OPTION, $data, false);
    }
    
    /**
     * Activate license key
     */
    public function activate(string $licenseKey): array|\WP_Error
    {
        if (empty($this->licenseServerUrl)) {
            return new \WP_Error('no_server', __('License server not configured', 'ai-seo-assistant'));
        }
        
        $response = wp_remote_post($this->licenseServerUrl . '/api/activate', [
            'timeout' => 30,
            'body' => [
                'license_key' => sanitize_text_field($licenseKey),
                'product_id' => $this->productId,
                'site_url' => home_url(),
                'site_name' => get_bloginfo('name'),
            ],
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['success'])) {
            return new \WP_Error(
                'activation_failed',
                $body['message'] ?? __('License activation failed', 'ai-seo-assistant')
            );
        }
        
        $license = [
            'key' => $licenseKey,
            'status' => 'active',
            'tier' => $body['tier'] ?? 'standard',
            'expires' => $body['expires'] ?? '',
            'activated_at' => current_time('mysql'),
            'last_check' => current_time('mysql'),
            'is_trial' => false,
            'trial_start' => '',
            'features' => $body['features'] ?? $this->getTierFeatures($body['tier'] ?? 'standard'),
            'site_count' => 1,
            'max_sites' => $body['max_sites'] ?? 1,
        ];
        
        $this->saveLicense($license);
        
        return [
            'success' => true,
            'message' => __('License activated successfully', 'ai-seo-assistant'),
            'license' => $license,
        ];
    }
    
    /**
     * Deactivate license
     */
    public function deactivate(): array|\WP_Error
    {
        $license = $this->getLicense();
        
        if (empty($license['key'])) {
            return new \WP_Error('no_license', __('No active license found', 'ai-seo-assistant'));
        }
        
        if (!empty($this->licenseServerUrl)) {
            wp_remote_post($this->licenseServerUrl . '/api/deactivate', [
                'timeout' => 30,
                'body' => [
                    'license_key' => $license['key'],
                    'product_id' => $this->productId,
                    'site_url' => home_url(),
                ],
            ]);
        }
        
        delete_option(self::LICENSE_OPTION);
        delete_transient(self::LICENSE_TRANSIENT);
        
        return [
            'success' => true,
            'message' => __('License deactivated', 'ai-seo-assistant'),
        ];
    }
    
    /**
     * Validate license with remote server
     */
    public function validate(): array|\WP_Error
    {
        $license = $this->getLicense();
        
        if (empty($license['key'])) {
            // Check if trial is active
            if ($this->isTrialActive()) {
                return ['valid' => true, 'status' => 'trial', 'tier' => 'trial'];
            }
            return new \WP_Error('no_license', __('No license found', 'ai-seo-assistant'));
        }
        
        // Check transient cache
        $cached = get_transient(self::LICENSE_TRANSIENT);
        if ($cached !== false) {
            return ['valid' => $cached === 'valid', 'status' => $license['status'], 'tier' => $license['tier']];
        }
        
        if (empty($this->licenseServerUrl)) {
            // Offline mode - check local expiration
            if (!empty($license['expires']) && strtotime($license['expires']) < time()) {
                $license['status'] = 'expired';
                $this->saveLicense($license);
                return new \WP_Error('expired', __('License has expired', 'ai-seo-assistant'));
            }
            return ['valid' => true, 'status' => $license['status'], 'tier' => $license['tier']];
        }
        
        $response = wp_remote_post($this->licenseServerUrl . '/api/validate', [
            'timeout' => 30,
            'body' => [
                'license_key' => $license['key'],
                'product_id' => $this->productId,
                'site_url' => home_url(),
            ],
        ]);
        
        if (is_wp_error($response)) {
            // Server unavailable, use local validation
            if (!empty($license['expires']) && strtotime($license['expires']) < time()) {
                return new \WP_Error('expired', __('License validation failed', 'ai-seo-assistant'));
            }
            return ['valid' => true, 'status' => $license['status'], 'tier' => $license['tier'], 'offline' => true];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['valid'])) {
            $license['status'] = $body['status'] ?? 'invalid';
            $this->saveLicense($license);
            set_transient(self::LICENSE_TRANSIENT, 'invalid', HOUR_IN_SECONDS);
            return new \WP_Error(
                $body['status'] ?? 'invalid',
                $body['message'] ?? __('License is not valid', 'ai-seo-assistant')
            );
        }
        
        // Update license info
        $license['status'] = 'active';
        $license['last_check'] = current_time('mysql');
        if (!empty($body['expires'])) {
            $license['expires'] = $body['expires'];
        }
        if (!empty($body['tier'])) {
            $license['tier'] = $body['tier'];
        }
        if (!empty($body['features'])) {
            $license['features'] = $body['features'];
        }
        $this->saveLicense($license);
        
        set_transient(self::LICENSE_TRANSIENT, 'valid', DAY_IN_SECONDS);
        
        return [
            'valid' => true,
            'status' => $license['status'],
            'tier' => $license['tier'],
            'expires' => $license['expires'],
        ];
    }
    
    /**
     * Check if license is valid
     */
    public function isValid(): bool
    {
        $validation = $this->validate();
        return !is_wp_error($validation) && !empty($validation['valid']);
    }
    
    /**
     * Get current license tier
     */
    public function getTier(): string
    {
        if ($this->isTrialActive()) {
            return 'trial';
        }
        $license = $this->getLicense();
        return $license['status'] === 'active' ? ($license['tier'] ?? 'free') : 'free';
    }
    
    /**
     * Check if feature is available for current tier
     */
    public function hasFeature(string $feature): bool
    {
        $tier = $this->getTier();
        
        $features = $this->getTierFeatures($tier);
        return in_array($feature, $features, true);
    }
    
    /**
     * Start trial mode
     */
    public function startTrial(): array
    {
        $license = $this->getLicense();
        
        if (!empty($license['key']) || $this->isTrialActive()) {
            return ['success' => false, 'message' => __('Trial already started or license exists', 'ai-seo-assistant')];
        }
        
        $license['is_trial'] = true;
        $license['trial_start'] = current_time('mysql');
        $license['tier'] = 'trial';
        $license['status'] = 'trial';
        $license['features'] = $this->getTierFeatures('trial');
        $this->saveLicense($license);
        
        return [
            'success' => true,
            'message' => sprintf(__('Trial started - %d days remaining', 'ai-seo-assistant'), self::TRIAL_DURATION_DAYS),
            'expires' => date('Y-m-d H:i:s', strtotime('+14 days')),
        ];
    }
    
    /**
     * Check if trial is active
     */
    public function isTrialActive(): bool
    {
        $license = $this->getLicense();
        
        if (empty($license['is_trial']) || empty($license['trial_start'])) {
            return false;
        }
        
        $trialEnd = strtotime($license['trial_start'] . ' + ' . self::TRIAL_DURATION_DAYS . ' days');
        return time() < $trialEnd;
    }
    
    /**
     * Get trial days remaining
     */
    public function getTrialDaysRemaining(): int
    {
        $license = $this->getLicense();
        
        if (empty($license['is_trial']) || empty($license['trial_start'])) {
            return 0;
        }
        
        $trialEnd = strtotime($license['trial_start'] . ' + ' . self::TRIAL_DURATION_DAYS . ' days');
        $remaining = ceil(($trialEnd - time()) / DAY_IN_SECONDS);
        
        return max(0, $remaining);
    }
    
    /**
     * Get features available for a tier
     */
    public function getTierFeatures(string $tier): array
    {
        $features = [
            'free' => [
                'basic_seo',
                'title_meta',
                'social_preview',
            ],
            'trial' => [
                'basic_seo',
                'title_meta',
                'social_preview',
                'sitemap',
                'schema_markup',
                'redirects',
                '404_monitor',
                'truseo_score',
                'focus_keyphrase',
                'lsi_keywords',
                'smart_tags',
                'link_assistant',
                'image_alt',
                'ai_repurposing',
                'ai_features',
            ],
            'starter' => [
                'basic_seo',
                'title_meta',
                'social_preview',
                'sitemap',
                'schema_markup',
                'redirects',
                '404_monitor',
                'truseo_score',
                'focus_keyphrase',
            ],
            'professional' => [
                'basic_seo',
                'title_meta',
                'social_preview',
                'sitemap',
                'extended_sitemaps',
                'schema_markup',
                'redirects',
                '404_monitor',
                'truseo_score',
                'focus_keyphrase',
                'lsi_keywords',
                'smart_tags',
                'serp_preview',
                'link_assistant',
                'image_alt',
                'seo_revisions',
                'import_export',
            ],
            'business' => [
                'basic_seo',
                'title_meta',
                'social_preview',
                'sitemap',
                'extended_sitemaps',
                'schema_markup',
                'redirects',
                '404_monitor',
                'truseo_score',
                'focus_keyphrase',
                'lsi_keywords',
                'smart_tags',
                'serp_preview',
                'link_assistant',
                'image_alt',
                'local_seo',
                'woocommerce_seo',
                'seo_revisions',
                'role_permissions',
                'import_export',
            ],
            'agency' => [
                'basic_seo',
                'title_meta',
                'social_preview',
                'sitemap',
                'extended_sitemaps',
                'schema_markup',
                'redirects',
                '404_monitor',
                'truseo_score',
                'focus_keyphrase',
                'lsi_keywords',
                'smart_tags',
                'serp_preview',
                'link_assistant',
                'image_alt',
                'ai_repurposing',
                'ai_alt_text',
                'ai_keypoints',
                'local_seo',
                'woocommerce_seo',
                'seo_revisions',
                'role_permissions',
                'import_export',
                'white_label',
                'priority_support',
                'api_access',
            ],
        ];
        
        return $features[$tier] ?? $features['free'];
    }
    
    /**
     * Check if current tier allows multiple sites
     */
    public function canAddSite(): bool
    {
        $license = $this->getLicense();
        return ($license['site_count'] ?? 0) < ($license['max_sites'] ?? 1);
    }
    
    /**
     * Get license status display
     */
    public function getStatusDisplay(): array
    {
        $license = $this->getLicense();
        $tier = $this->getTier();
        
        if ($this->isTrialActive()) {
            return [
                'label' => __('Trial', 'ai-seo-assistant'),
                'class' => 'notice-warning',
                'message' => sprintf(
                    __('%d days remaining in trial', 'ai-seo-assistant'),
                    $this->getTrialDaysRemaining()
                ),
            ];
        }
        
        if (empty($license['key'])) {
            return [
                'label' => __('Free', 'ai-seo-assistant'),
                'class' => 'notice-info',
                'message' => __('Limited features - upgrade for full access', 'ai-seo-assistant'),
            ];
        }
        
        if ($license['status'] === 'expired') {
            return [
                'label' => __('Expired', 'ai-seo-assistant'),
                'class' => 'notice-error',
                'message' => __('Your license has expired - please renew', 'ai-seo-assistant'),
            ];
        }
        
        if ($license['status'] !== 'active') {
            return [
                'label' => __('Inactive', 'ai-seo-assistant'),
                'class' => 'notice-warning',
                'message' => __('License is not active', 'ai-seo-assistant'),
            ];
        }
        
        return [
            'label' => ucfirst($tier),
            'class' => 'notice-success',
            'message' => !empty($license['expires']) 
                ? sprintf(__('Valid until %s', 'ai-seo-assistant'), date_i18n(get_option('date_format'), strtotime($license['expires'])))
                : __('Active license', 'ai-seo-assistant'),
        ];
    }
}

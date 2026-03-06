<?php

namespace AISEOAssistant;

class LicenseAPI
{
    private LicenseManager $licenseManager;
    
    public function __construct(LicenseManager $licenseManager)
    {
        $this->licenseManager = $licenseManager;
    }
    
    /**
     * Register REST API routes
     */
    public function register(): void
    {
        // Public endpoints (no auth required for activation)
        register_rest_route('aiseoassistant/v1', '/license/activate', [
            'methods' => 'POST',
            'callback' => [$this, 'activate'],
            'permission_callback' => '__return_true',
            'args' => [
                'license_key' => [
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        register_rest_route('aiseoassistant/v1', '/license/deactivate', [
            'methods' => 'POST',
            'callback' => [$this, 'deactivate'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
        
        register_rest_route('aiseoassistant/v1', '/license/validate', [
            'methods' => 'GET',
            'callback' => [$this, 'validate'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
        
        register_rest_route('aiseoassistant/v1', '/license/status', [
            'methods' => 'GET',
            'callback' => [$this, 'status'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
        
        register_rest_route('aiseoassistant/v1', '/license/trial', [
            'methods' => 'POST',
            'callback' => [$this, 'startTrial'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
        
        register_rest_route('aiseoassistant/v1', '/license/features', [
            'methods' => 'GET',
            'callback' => [$this, 'getFeatures'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
        
        // Check feature availability
        register_rest_route('aiseoassistant/v1', '/license/check-feature', [
            'methods' => 'GET',
            'callback' => [$this, 'checkFeature'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'feature' => [
                    'type' => 'string',
                    'required' => true,
                ],
            ],
        ]);
    }
    
    /**
     * Activate license
     */
    public function activate(\WP_REST_Request $request): \WP_REST_Response
    {
        $licenseKey = $request->get_param('license_key');
        
        // Verify with license key format (e.g., AISEO-XXXX-XXXX-XXXX)
        if (!$this->isValidLicenseFormat($licenseKey)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Invalid license key format', 'ai-seo-assistant'),
            ], 400);
        }
        
        $result = $this->licenseManager->activate($licenseKey);
        
        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code(),
            ], 400);
        }
        
        return new \WP_REST_Response($result, 200);
    }
    
    /**
     * Deactivate license
     */
    public function deactivate(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = $this->licenseManager->deactivate();
        
        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }
        
        return new \WP_REST_Response($result, 200);
    }
    
    /**
     * Validate current license
     */
    public function validate(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = $this->licenseManager->validate();
        
        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'valid' => false,
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code(),
            ], 200);
        }
        
        return new \WP_REST_Response([
            'valid' => true,
            'status' => $result['status'],
            'tier' => $result['tier'],
            'expires' => $result['expires'] ?? null,
        ], 200);
    }
    
    /**
     * Get current license status
     */
    public function status(\WP_REST_Request $request): \WP_REST_Response
    {
        $license = $this->licenseManager->getLicense();
        $display = $this->licenseManager->getStatusDisplay();
        
        return new \WP_REST_Response([
            'license' => [
                'key' => $this->maskLicenseKey($license['key']),
                'status' => $license['status'],
                'tier' => $this->licenseManager->getTier(),
                'expires' => $license['expires'] ?: null,
                'is_trial' => $license['is_trial'],
                'trial_days_remaining' => $this->licenseManager->isTrialActive() ? $this->licenseManager->getTrialDaysRemaining() : 0,
            ],
            'display' => $display,
            'valid' => $this->licenseManager->isValid(),
        ], 200);
    }
    
    /**
     * Start trial
     */
    public function startTrial(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = $this->licenseManager->startTrial();
        
        if (!$result['success']) {
            return new \WP_REST_Response($result, 400);
        }
        
        return new \WP_REST_Response($result, 200);
    }
    
    /**
     * Get available features for current tier
     */
    public function getFeatures(\WP_REST_Request $request): \WP_REST_Response
    {
        $tier = $this->licenseManager->getTier();
        $features = $this->licenseManager->getTierFeatures($tier);
        
        // Feature descriptions
        $featureInfo = [
            'basic_seo' => ['name' => __('Basic SEO', 'ai-seo-assistant'), 'description' => __('Title and meta description editing', 'ai-seo-assistant')],
            'title_meta' => ['name' => __('Title & Meta', 'ai-seo-assistant'), 'description' => __('Advanced title and meta templates', 'ai-seo-assistant')],
            'social_preview' => ['name' => __('Social Preview', 'ai-seo-assistant'), 'description' => __('Open Graph and Twitter Cards', 'ai-seo-assistant')],
            'sitemap' => ['name' => __('XML Sitemap', 'ai-seo-assistant'), 'description' => __('Auto-generated XML sitemaps', 'ai-seo-assistant')],
            'extended_sitemaps' => ['name' => __('Extended Sitemaps', 'ai-seo-assistant'), 'description' => __('Video, News, Image, RSS sitemaps', 'ai-seo-assistant')],
            'schema_markup' => ['name' => __('Schema Markup', 'ai-seo-assistant'), 'description' => __('JSON-LD structured data', 'ai-seo-assistant')],
            'redirects' => ['name' => __('Redirects', 'ai-seo-assistant'), 'description' => __('301/302 redirect manager', 'ai-seo-assistant')],
            '404_monitor' => ['name' => __('404 Monitor', 'ai-seo-assistant'), 'description' => __('Track and fix 404 errors', 'ai-seo-assistant')],
            'truseo_score' => ['name' => __('TruSEO Score', 'ai-seo-assistant'), 'description' => __('Real-time content analysis', 'ai-seo-assistant')],
            'focus_keyphrase' => ['name' => __('Focus Keyphrase', 'ai-seo-assistant'), 'description' => __('Keyphrase optimization analysis', 'ai-seo-assistant')],
            'lsi_keywords' => ['name' => __('LSI Keywords', 'ai-seo-assistant'), 'description' => __('Semantic keyword suggestions', 'ai-seo-assistant')],
            'smart_tags' => ['name' => __('Smart Tags', 'ai-seo-assistant'), 'description' => __('AI-powered tag suggestions', 'ai-seo-assistant')],
            'serp_preview' => ['name' => __('SERP Preview', 'ai-seo-assistant'), 'description' => __('Google search preview', 'ai-seo-assistant')],
            'link_assistant' => ['name' => __('Link Assistant', 'ai-seo-assistant'), 'description' => __('Internal linking suggestions', 'ai-seo-assistant')],
            'image_alt' => ['name' => __('Image Alt Text', 'ai-seo-assistant'), 'description' => __('AI alt text generation', 'ai-seo-assistant')],
            'ai_repurposing' => ['name' => __('AI Repurposing', 'ai-seo-assistant'), 'description' => __('Repurpose content for different platforms', 'ai-seo-assistant')],
            'ai_alt_text' => ['name' => __('AI Alt Text', 'ai-seo-assistant'), 'description' => __('Automatic image alt text', 'ai-seo-assistant')],
            'ai_keypoints' => ['name' => __('AI Key Points', 'ai-seo-assistant'), 'description' => __('Auto-generate TL;DR summaries', 'ai-seo-assistant')],
            'local_seo' => ['name' => __('Local SEO', 'ai-seo-assistant'), 'description' => __('Local business schema and optimization', 'ai-seo-assistant')],
            'woocommerce_seo' => ['name' => __('WooCommerce SEO', 'ai-seo-assistant'), 'description' => __('Product and category optimization', 'ai-seo-assistant')],
            'seo_revisions' => ['name' => __('SEO Revisions', 'ai-seo-assistant'), 'description' => __('Track SEO metadata changes', 'ai-seo-assistant')],
            'role_permissions' => ['name' => __('Role Permissions', 'ai-seo-assistant'), 'description' => __('User role-based access control', 'ai-seo-assistant')],
            'import_export' => ['name' => __('Import/Export', 'ai-seo-assistant'), 'description' => __('Import from other SEO plugins', 'ai-seo-assistant')],
            'white_label' => ['name' => __('White Label', 'ai-seo-assistant'), 'description' => __('Remove branding', 'ai-seo-assistant')],
            'priority_support' => ['name' => __('Priority Support', 'ai-seo-assistant'), 'description' => __('Priority email support', 'ai-seo-assistant')],
            'api_access' => ['name' => __('API Access', 'ai-seo-assistant'), 'description' => __('Full REST API access', 'ai-seo-assistant')],
        ];
        
        $featuresWithInfo = [];
        foreach ($features as $feature) {
            $featuresWithInfo[] = array_merge(
                ['id' => $feature],
                $featureInfo[$feature] ?? ['name' => $feature, 'description' => '']
            );
        }
        
        return new \WP_REST_Response([
            'tier' => $tier,
            'features' => $featuresWithInfo,
            'all_tiers' => [
                ['id' => 'free', 'name' => __('Free', 'ai-seo-assistant'), 'max_sites' => 1],
                ['id' => 'trial', 'name' => __('Trial', 'ai-seo-assistant'), 'max_sites' => 1],
                ['id' => 'starter', 'name' => __('Starter', 'ai-seo-assistant'), 'max_sites' => 1],
                ['id' => 'professional', 'name' => __('Professional', 'ai-seo-assistant'), 'max_sites' => 3],
                ['id' => 'business', 'name' => __('Business', 'ai-seo-assistant'), 'max_sites' => 10],
                ['id' => 'agency', 'name' => __('Agency', 'ai-seo-assistant'), 'max_sites' => 100],
            ],
        ], 200);
    }
    
    /**
     * Check if specific feature is available
     */
    public function checkFeature(\WP_REST_Request $request): \WP_REST_Response
    {
        $feature = sanitize_text_field($request->get_param('feature'));
        $hasFeature = $this->licenseManager->hasFeature($feature);
        
        return new \WP_REST_Response([
            'feature' => $feature,
            'available' => $hasFeature,
            'tier' => $this->licenseManager->getTier(),
        ], 200);
    }
    
    /**
     * Validate license key format
     */
    private function isValidLicenseFormat(string $key): bool
    {
        // Format: AISEO-XXXX-XXXX-XXXX or AISEO-XXXXXXXXXXXXXXXX
        return preg_match('/^AISEO-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/i', $key) === 1
            || preg_match('/^AISEO-[A-Z0-9]{16}$/i', $key) === 1;
    }
    
    /**
     * Mask license key for display
     */
    private function maskLicenseKey(string $key): string
    {
        if (empty($key)) {
            return '';
        }
        
        $parts = explode('-', $key);
        if (count($parts) === 4) {
            return $parts[0] . '-****-****-' . $parts[3];
        }
        
        return substr($key, 0, 8) . '****' . substr($key, -4);
    }
}

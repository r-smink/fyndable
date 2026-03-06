<?php

namespace AISEOAssistant;

class FeatureGate
{
    private LicenseManager $licenseManager;
    
    // Feature categories for easier management
    private const FREE_FEATURES = [
        'basic_seo',
        'title_meta',
        'social_preview',
    ];
    
    private const TRIAL_FEATURES = [
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
    ];
    
    private const STARTER_FEATURES = [
        'basic_seo',
        'title_meta',
        'social_preview',
        'sitemap',
        'schema_markup',
        'redirects',
        '404_monitor',
        'truseo_score',
        'focus_keyphrase',
    ];
    
    private const PROFESSIONAL_FEATURES = [
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
    ];
    
    private const BUSINESS_FEATURES = [
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
    ];
    
    private const AGENCY_FEATURES = [
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
    ];
    
    public function __construct(LicenseManager $licenseManager)
    {
        $this->licenseManager = $licenseManager;
    }
    
    /**
     * Check if a feature is available for the current license tier
     */
    public function canAccess(string $feature): bool
    {
        $tier = $this->licenseManager->getTier();
        
        // Check if user is admin - bypass restrictions
        if (current_user_can('manage_options')) {
            return true;
        }
        
        $features = $this->getFeaturesForTier($tier);
        return in_array($feature, $features, true);
    }
    
    /**
     * Get all features available for a specific tier
     */
    public function getFeaturesForTier(string $tier): array
    {
        $featureMap = [
            'free' => self::FREE_FEATURES,
            'trial' => self::TRIAL_FEATURES,
            'starter' => self::STARTER_FEATURES,
            'professional' => self::PROFESSIONAL_FEATURES,
            'business' => self::BUSINESS_FEATURES,
            'agency' => self::AGENCY_FEATURES,
        ];
        
        return $featureMap[$tier] ?? self::FREE_FEATURES;
    }
    
    /**
     * Get feature display name and description
     */
    public function getFeatureInfo(string $feature): array
    {
        $info = [
            'basic_seo' => [
                'name' => __('Basic SEO', 'ai-seo-assistant'),
                'description' => __('Title and meta description editing', 'ai-seo-assistant'),
                'icon' => 'dashicons-admin-generic',
            ],
            'title_meta' => [
                'name' => __('Title & Meta', 'ai-seo-assistant'),
                'description' => __('Advanced title and meta templates', 'ai-seo-assistant'),
                'icon' => 'dashicons-text',
            ],
            'social_preview' => [
                'name' => __('Social Preview', 'ai-seo-assistant'),
                'description' => __('Open Graph and Twitter Cards', 'ai-seo-assistant'),
                'icon' => 'dashicons-share',
            ],
            'sitemap' => [
                'name' => __('XML Sitemap', 'ai-seo-assistant'),
                'description' => __('Auto-generated XML sitemaps', 'ai-seo-assistant'),
                'icon' => 'dashicons-networking',
            ],
            'extended_sitemaps' => [
                'name' => __('Extended Sitemaps', 'ai-seo-assistant'),
                'description' => __('Video, News, Image, RSS sitemaps', 'ai-seo-assistant'),
                'icon' => 'dashicons-media-video',
            ],
            'schema_markup' => [
                'name' => __('Schema Markup', 'ai-seo-assistant'),
                'description' => __('JSON-LD structured data', 'ai-seo-assistant'),
                'icon' => 'dashicons-editor-code',
            ],
            'redirects' => [
                'name' => __('Redirects', 'ai-seo-assistant'),
                'description' => __('301/302 redirect manager', 'ai-seo-assistant'),
                'icon' => 'dashicons-migrate',
            ],
            '404_monitor' => [
                'name' => __('404 Monitor', 'ai-seo-assistant'),
                'description' => __('Track and fix 404 errors', 'ai-seo-assistant'),
                'icon' => 'dashicons-warning',
            ],
            'truseo_score' => [
                'name' => __('TruSEO Score', 'ai-seo-assistant'),
                'description' => __('Real-time content analysis', 'ai-seo-assistant'),
                'icon' => 'dashicons-chart-line',
            ],
            'focus_keyphrase' => [
                'name' => __('Focus Keyphrase', 'ai-seo-assistant'),
                'description' => __('Keyphrase optimization analysis', 'ai-seo-assistant'),
                'icon' => 'dashicons-search',
            ],
            'lsi_keywords' => [
                'name' => __('LSI Keywords', 'ai-seo-assistant'),
                'description' => __('Semantic keyword suggestions', 'ai-seo-assistant'),
                'icon' => 'dashicons-tag',
            ],
            'smart_tags' => [
                'name' => __('Smart Tags', 'ai-seo-assistant'),
                'description' => __('AI-powered tag suggestions', 'ai-seo-assistant'),
                'icon' => 'dashicons-tagcloud',
            ],
            'serp_preview' => [
                'name' => __('SERP Preview', 'ai-seo-assistant'),
                'description' => __('Google search preview', 'ai-seo-assistant'),
                'icon' => 'dashicons-google',
            ],
            'link_assistant' => [
                'name' => __('Link Assistant', 'ai-seo-assistant'),
                'description' => __('Internal linking suggestions', 'ai-seo-assistant'),
                'icon' => 'dashicons-admin-links',
            ],
            'image_alt' => [
                'name' => __('Image Alt Text', 'ai-seo-assistant'),
                'description' => __('AI alt text generation', 'ai-seo-assistant'),
                'icon' => 'dashicons-format-image',
            ],
            'ai_repurposing' => [
                'name' => __('AI Repurposing', 'ai-seo-assistant'),
                'description' => __('Repurpose content for different platforms', 'ai-seo-assistant'),
                'icon' => 'dashicons-update',
            ],
            'ai_alt_text' => [
                'name' => __('AI Alt Text', 'ai-seo-assistant'),
                'description' => __('Automatic image alt text', 'ai-seo-assistant'),
                'icon' => 'dashicons-format-image',
            ],
            'ai_keypoints' => [
                'name' => __('AI Key Points', 'ai-seo-assistant'),
                'description' => __('Auto-generate TL;DR summaries', 'ai-seo-assistant'),
                'icon' => 'dashicons-list-view',
            ],
            'local_seo' => [
                'name' => __('Local SEO', 'ai-seo-assistant'),
                'description' => __('Local business schema and optimization', 'ai-seo-assistant'),
                'icon' => 'dashicons-location',
            ],
            'woocommerce_seo' => [
                'name' => __('WooCommerce SEO', 'ai-seo-assistant'),
                'description' => __('Product and category optimization', 'ai-seo-assistant'),
                'icon' => 'dashicons-cart',
            ],
            'seo_revisions' => [
                'name' => __('SEO Revisions', 'ai-seo-assistant'),
                'description' => __('Track SEO metadata changes', 'ai-seo-assistant'),
                'icon' => 'dashicons-backup',
            ],
            'role_permissions' => [
                'name' => __('Role Permissions', 'ai-seo-assistant'),
                'description' => __('User role-based access control', 'ai-seo-assistant'),
                'icon' => 'dashicons-groups',
            ],
            'import_export' => [
                'name' => __('Import/Export', 'ai-seo-assistant'),
                'description' => __('Import from other SEO plugins', 'ai-seo-assistant'),
                'icon' => 'dashicons-migrate',
            ],
            'white_label' => [
                'name' => __('White Label', 'ai-seo-assistant'),
                'description' => __('Remove branding', 'ai-seo-assistant'),
                'icon' => 'dashicons-hidden',
            ],
            'priority_support' => [
                'name' => __('Priority Support', 'ai-seo-assistant'),
                'description' => __('Priority email support', 'ai-seo-assistant'),
                'icon' => 'dashicons-email-alt',
            ],
            'api_access' => [
                'name' => __('API Access', 'ai-seo-assistant'),
                'description' => __('Full REST API access', 'ai-seo-assistant'),
                'icon' => 'dashicons-rest-api',
            ],
        ];
        
        return $info[$feature] ?? [
            'name' => $feature,
            'description' => '',
            'icon' => 'dashicons-admin-generic',
        ];
    }
    
    /**
     * Get upgrade message for a feature
     */
    public function getUpgradeMessage(string $feature): string
    {
        $tierRequirements = [
            'basic_seo' => 'free',
            'title_meta' => 'free',
            'social_preview' => 'free',
            'sitemap' => 'starter',
            'schema_markup' => 'starter',
            'redirects' => 'starter',
            '404_monitor' => 'starter',
            'truseo_score' => 'starter',
            'focus_keyphrase' => 'starter',
            'extended_sitemaps' => 'professional',
            'lsi_keywords' => 'professional',
            'smart_tags' => 'professional',
            'serp_preview' => 'professional',
            'link_assistant' => 'professional',
            'image_alt' => 'professional',
            'seo_revisions' => 'professional',
            'import_export' => 'professional',
            'local_seo' => 'business',
            'woocommerce_seo' => 'business',
            'role_permissions' => 'business',
            'ai_repurposing' => 'agency',
            'ai_alt_text' => 'agency',
            'ai_keypoints' => 'agency',
            'white_label' => 'agency',
            'priority_support' => 'agency',
            'api_access' => 'agency',
        ];
        
        $requiredTier = $tierRequirements[$feature] ?? 'agency';
        $tierNames = [
            'free' => __('Free', 'ai-seo-assistant'),
            'starter' => __('Starter', 'ai-seo-assistant'),
            'professional' => __('Professional', 'ai-seo-assistant'),
            'business' => __('Business', 'ai-seo-assistant'),
            'agency' => __('Agency', 'ai-seo-assistant'),
        ];
        
        return sprintf(
            __('This feature requires the %s plan or higher. %s', 'ai-seo-assistant'),
            $tierNames[$requiredTier] ?? $requiredTier,
            '<a href="' . admin_url('admin.php?page=ai-seo-assistant-license') . '">' . __('Upgrade now', 'ai-seo-assistant') . '</a>'
        );
    }
    
    /**
     * Render feature availability indicator
     */
    public function renderFeatureIndicator(string $feature): void
    {
        $hasAccess = $this->canAccess($feature);
        $info = $this->getFeatureInfo($feature);
        
        if ($hasAccess) {
            echo '<span class="aiseo-feature-available dashicons dashicons-yes-alt" title="' . esc_attr__('Available in your plan', 'ai-seo-assistant') . '"></span>';
        } else {
            echo '<span class="aiseo-feature-locked dashicons dashicons-lock" title="' . esc_attr($this->getUpgradeMessage($feature)) . '"></span>';
        }
    }
    
    /**
     * Filter admin menu items based on license
     */
    public function filterMenuItems(array $items): array
    {
        $filtered = [];
        
        foreach ($items as $item) {
            $requiredFeature = $item['feature'] ?? 'basic_seo';
            
            if ($this->canAccess($requiredFeature)) {
                $filtered[] = $item;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Check if user can use AI features
     */
    public function canUseAI(): bool
    {
        return $this->canAccess('ai_features') || 
               $this->canAccess('ai_repurposing') || 
               $this->canAccess('ai_alt_text') ||
               $this->canAccess('ai_keypoints');
    }
    
    /**
     * Check if user can use advanced SEO features
     */
    public function canUseAdvancedSEO(): bool
    {
        return $this->canAccess('schema_markup') ||
               $this->canAccess('redirects') ||
               $this->canAccess('404_monitor') ||
               $this->canAccess('truseo_score');
    }
    
    /**
     * Get current usage limits based on license tier
     */
    public function getUsageLimits(): array
    {
        $tier = $this->licenseManager->getTier();
        
        $limits = [
            'free' => [
                'keywords_tracked' => 5,
                'serp_snapshots' => 10,
                'ai_requests' => 0,
                'sites' => 1,
            ],
            'trial' => [
                'keywords_tracked' => 50,
                'serp_snapshots' => 100,
                'ai_requests' => 100,
                'sites' => 1,
            ],
            'starter' => [
                'keywords_tracked' => 25,
                'serp_snapshots' => 50,
                'ai_requests' => 0,
                'sites' => 1,
            ],
            'professional' => [
                'keywords_tracked' => 100,
                'serp_snapshots' => 500,
                'ai_requests' => 200,
                'sites' => 3,
            ],
            'business' => [
                'keywords_tracked' => 500,
                'serp_snapshots' => 2000,
                'ai_requests' => 1000,
                'sites' => 10,
            ],
            'agency' => [
                'keywords_tracked' => -1, // unlimited
                'serp_snapshots' => -1, // unlimited
                'ai_requests' => -1, // unlimited
                'sites' => 100,
            ],
        ];
        
        return $limits[$tier] ?? $limits['free'];
    }
    
    /**
     * Check if user has exceeded a limit
     */
    public function hasExceededLimit(string $limitType, int $currentValue): bool
    {
        $limits = $this->getUsageLimits();
        $limit = $limits[$limitType] ?? 0;
        
        // -1 means unlimited
        if ($limit === -1) {
            return false;
        }
        
        return $currentValue >= $limit;
    }
    
    /**
     * Get remaining quota for a limit type
     */
    public function getRemainingQuota(string $limitType, int $currentValue): int
    {
        $limits = $this->getUsageLimits();
        $limit = $limits[$limitType] ?? 0;
        
        if ($limit === -1) {
            return -1; // unlimited
        }
        
        return max(0, $limit - $currentValue);
    }
}

<?php

namespace SSEOAISaaS;

/**
 * License Feature Manager
 * 
 * Manages advanced feature toggles per license/tenant.
 * Allows administrators to override default tier-based features
 * for individual licenses from the SaaS portal.
 */
class LicenseFeatureManager
{
    private TenantRepository $tenants;
    private LicenseKeyGenerator $licenseGenerator;
    
    // All available features in the system
    public const ALL_FEATURES = [
        // Core SEO (All tiers)
        'truseo' => ['name' => 'TruSEO Score', 'tier' => 'free', 'category' => 'Core SEO'],
        'smart_tags' => ['name' => 'Smart Meta Tags', 'tier' => 'free', 'category' => 'Core SEO'],
        'sitemap' => ['name' => 'XML Sitemap', 'tier' => 'free', 'category' => 'Core SEO'],
        'robots_txt' => ['name' => 'Robots.txt Editor', 'tier' => 'free', 'category' => 'Core SEO'],
        'open_graph' => ['name' => 'Open Graph', 'tier' => 'free', 'category' => 'Core SEO'],
        'canonical' => ['name' => 'Canonical URLs', 'tier' => 'free', 'category' => 'Core SEO'],
        'breadcrumbs' => ['name' => 'Breadcrumbs', 'tier' => 'free', 'category' => 'Core SEO'],
        'hreflang' => ['name' => 'Hreflang', 'tier' => 'free', 'category' => 'Core SEO'],
        'lsi_keywords' => ['name' => 'LSI Keywords', 'tier' => 'free', 'category' => 'Core SEO'],
        'page_speed' => ['name' => 'PageSpeed Insights', 'tier' => 'free', 'category' => 'Core SEO'],
        'readability' => ['name' => 'Readability Score', 'tier' => 'free', 'category' => 'Core SEO'],
        'index_now' => ['name' => 'IndexNow', 'tier' => 'free', 'category' => 'Core SEO'],
        'content_calendar' => ['name' => 'Content Calendar', 'tier' => 'free', 'category' => 'Core SEO'],
        'smart_linking' => ['name' => 'Smart Internal Linking', 'tier' => 'free', 'category' => 'Core SEO'],
        'eeat_validator' => ['name' => 'E-E-A-T Validator', 'tier' => 'free', 'category' => 'Core SEO'],
        'video_seo' => ['name' => 'Video SEO', 'tier' => 'free', 'category' => 'Core SEO'],
        'faq_schema' => ['name' => 'FAQ Schema', 'tier' => 'free', 'category' => 'Core SEO'],
        'ai_image_gen' => ['name' => 'AI Image Generator', 'tier' => 'free', 'category' => 'Core SEO'],
        
        // Starter+ Features
        'link_assistant' => ['name' => 'Link Assistant', 'tier' => 'starter', 'category' => 'Content Tools'],
        'redirect_manager' => ['name' => 'Redirect Manager', 'tier' => 'starter', 'category' => 'Content Tools'],
        'image_alt_gen' => ['name' => 'Image Alt Generator', 'tier' => 'starter', 'category' => 'Content Tools'],
        'content_rewriter' => ['name' => 'Content Rewriter', 'tier' => 'starter', 'category' => 'Content Tools'],
        
        // Professional+ Features
        'schema_markup' => ['name' => 'Schema Markup', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'local_seo' => ['name' => 'Local SEO', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        '404_monitor' => ['name' => '404 Monitor', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'rank_tracker' => ['name' => 'Rank Tracker', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'seo_reports' => ['name' => 'SEO Report Export', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'woocommerce_seo' => ['name' => 'WooCommerce SEO', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'content_optimizer' => ['name' => 'Content Optimizer', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'serp_competitor' => ['name' => 'SERP Competitor', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'topic_clusters' => ['name' => 'Topic Clusters', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'keyword_difficulty' => ['name' => 'Keyword Difficulty', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'content_brief' => ['name' => 'Content Brief', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'keyword_explorer' => ['name' => 'Keyword Explorer', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'gsc_dashboard' => ['name' => 'GSC Dashboard', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'serp_features' => ['name' => 'SERP Feature Tracker', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'backlink_analyzer' => ['name' => 'Backlink Analyzer', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'competitor_research' => ['name' => 'Competitor Research', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'international_seo' => ['name' => 'International SEO', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'technical_audit' => ['name' => 'Technical SEO Auditor', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        'advanced_backlinks' => ['name' => 'Advanced Backlinks', 'tier' => 'professional', 'category' => 'Advanced SEO'],
        
        // Business+ Features
        'ai_content_writer' => ['name' => 'AI Content Writer', 'tier' => 'business', 'category' => 'AI Generation'],
        'content_repurposer' => ['name' => 'AI Repurposer', 'tier' => 'business', 'category' => 'AI Generation'],
        'bulk_optimizer' => ['name' => 'Bulk AI Optimizer', 'tier' => 'business', 'category' => 'AI Generation'],
        'content_decay' => ['name' => 'Content Decay Monitor', 'tier' => 'business', 'category' => 'AI Generation'],
        'audit_service' => ['name' => 'Audit Service', 'tier' => 'business', 'category' => 'AI Generation'],
        'content_optimizer_calibration' => ['name' => 'Content Optimizer Calibration', 'tier' => 'business', 'category' => 'Advanced SEO', 'is_beta' => true],
        'advanced_backlinks' => ['name' => 'Advanced Backlinks / DataForSEO', 'tier' => 'business', 'category' => 'Advanced SEO', 'is_beta' => true],
        'content_workflow' => ['name' => 'Content Workflow', 'tier' => 'business', 'category' => 'Advanced SEO', 'is_beta' => true],
        'prompt_template_library' => ['name' => 'Prompt Template Library', 'tier' => 'business', 'category' => 'AI Generation', 'is_beta' => true],
        
        // Agency+ Features
        'seo_revisions' => ['name' => 'SEO Revisions', 'tier' => 'agency', 'category' => 'Agency Tools'],
        'plagiarism_checker' => ['name' => 'Plagiarism Checker', 'tier' => 'agency', 'category' => 'Agency Tools'],
        'white_label' => ['name' => 'White Label', 'tier' => 'agency', 'category' => 'Agency Tools'],
    ];
    
    public function __construct(TenantRepository $tenants, LicenseKeyGenerator $licenseGenerator)
    {
        $this->tenants = $tenants;
        $this->licenseGenerator = $licenseGenerator;
    }
    
    /**
     * Get all features organized by category
     */
    public function getAllFeatures(): array
    {
        $features = [];
        foreach (self::ALL_FEATURES as $key => $config) {
            if (!isset($features[$config['category']])) {
                $features[$config['category']] = [];
            }
            $config['is_beta'] = $config['is_beta'] ?? false;
            $features[$config['category']][$key] = $config;
        }
        return $features;
    }
    
    /**
     * Get features available for a specific tier
     */
    public function getFeaturesForTier(string $tier): array
    {
        $tierLevels = ['free' => 0, 'starter' => 1, 'early_adopters' => 1, 'professional' => 2, 'business' => 3, 'agency' => 4, 'dev' => 5];
        $tierLevel = $tierLevels[$tier] ?? 0;
        
        $available = [];
        foreach (self::ALL_FEATURES as $key => $config) {
            $featureLevel = $tierLevels[$config['tier']] ?? 0;
            if ($featureLevel <= $tierLevel || $tier === 'dev') {
                $config['is_beta'] = $config['is_beta'] ?? false;
                $available[$key] = $config;
            }
        }
        return $available;
    }
    
    /**
     * Get feature overrides for a license
     */
    public function getLicenseFeatures(string $licenseKey): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_license_keys';

        // Ensure the features column exists before querying
        $this->maybeAddFeaturesColumn('sseo_ai_license_keys');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT features FROM {$table} WHERE license_key = %s",
            $licenseKey
        ));

        if (!$row || empty($row->features)) {
            return null;
        }

        return json_decode($row->features, true);
    }
    
    /**
     * Get feature overrides for a tenant
     */
    public function getTenantFeatures(string $tenantKey): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_tenants';

        // Ensure the features column exists before querying
        $this->maybeAddFeaturesColumn('sseo_ai_tenants');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT features FROM {$table} WHERE tenant_key = %s",
            $tenantKey
        ));

        if (!$row || empty($row->features)) {
            return null;
        }

        return json_decode($row->features, true);
    }
    
    /**
     * Update feature overrides for a license
     */
    public function updateLicenseFeatures(string $licenseKey, array $features): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_license_keys';
        
        // First ensure the features column exists
        $this->maybeAddFeaturesColumn('sseo_ai_license_keys');
        
        $result = $wpdb->update(
            $table,
            ['features' => json_encode($features)],
            ['license_key' => $licenseKey],
            ['%s'],
            ['%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Update feature overrides for a tenant
     */
    public function updateTenantFeatures(string $tenantKey, array $features): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_tenants';
        
        // First ensure the features column exists
        $this->maybeAddFeaturesColumn('sseo_ai_tenants');
        
        $result = $wpdb->update(
            $table,
            ['features' => json_encode($features)],
            ['tenant_key' => $tenantKey],
            ['%s'],
            ['%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Get effective features (tier defaults + overrides)
     */
    public function getEffectiveFeatures(string $licenseKey, ?string $tenantKey = null): array
    {
        // Get license info
        $license = $this->licenseGenerator->getLicense($licenseKey);
        if (!$license) {
            return [];
        }
        
        // Start with tier defaults
        $tier = $license['tier'] ?? 'starter';
        $effective = $this->getFeaturesForTier($tier);
        
        // Apply license-level overrides
        $licenseOverrides = $this->getLicenseFeatures($licenseKey);
        if ($licenseOverrides) {
            foreach ($licenseOverrides as $feature => $enabled) {
                if ($enabled && !isset($effective[$feature])) {
                    // Add feature that's not in tier
                    if (isset(self::ALL_FEATURES[$feature])) {
                        $added = self::ALL_FEATURES[$feature];
                        $added['is_beta'] = $added['is_beta'] ?? false;
                        $effective[$feature] = $added;
                    }
                } elseif (!$enabled && isset($effective[$feature])) {
                    // Remove feature from tier
                    unset($effective[$feature]);
                }
            }
        }
        
        // Apply tenant-level overrides (if provided)
        if ($tenantKey) {
            $tenantOverrides = $this->getTenantFeatures($tenantKey);
            if ($tenantOverrides) {
                foreach ($tenantOverrides as $feature => $enabled) {
                    if ($enabled && !isset($effective[$feature])) {
                        if (isset(self::ALL_FEATURES[$feature])) {
                            $added = self::ALL_FEATURES[$feature];
                            $added['is_beta'] = $added['is_beta'] ?? false;
                            $effective[$feature] = $added;
                        }
                    } elseif (!$enabled && isset($effective[$feature])) {
                        unset($effective[$feature]);
                    }
                }
            }
        }
        
        return $effective;
    }
    
    /**
     * Check if a specific feature is enabled
     */
    public function hasFeature(string $licenseKey, string $feature, ?string $tenantKey = null): bool
    {
        $effective = $this->getEffectiveFeatures($licenseKey, $tenantKey);
        return isset($effective[$feature]);
    }

    /**
     * Check if a feature is marked as beta.
     */
    public function isBeta(string $feature): bool
    {
        return (self::ALL_FEATURES[$feature]['is_beta'] ?? false) === true;
    }
    
    /**
     * Add features column to table if it doesn't exist
     */
    private function maybeAddFeaturesColumn(string $tableName): void
    {
        global $wpdb;
        $fullTable = $wpdb->prefix . $tableName;
        
        // Check if column exists
        $columnExists = $wpdb->get_results(
            "SHOW COLUMNS FROM {$fullTable} LIKE 'features'"
        );
        
        if (empty($columnExists)) {
            $wpdb->query("ALTER TABLE {$fullTable} ADD COLUMN features longtext DEFAULT NULL");
        }
    }
    
    /**
     * Get feature toggle UI data for admin
     */
    public function getFeatureToggleData(string $licenseKey): array
    {
        $license = $this->licenseGenerator->getLicense($licenseKey);
        if (!$license) {
            return [];
        }
        
        $tier = $license['tier'] ?? 'starter';
        $tierFeatures = $this->getFeaturesForTier($tier);
        $overrides = $this->getLicenseFeatures($licenseKey) ?? [];
        
        $categories = $this->getAllFeatures();
        $toggleData = [];
        
        foreach ($categories as $category => $features) {
            $toggleData[$category] = [];
            foreach ($features as $key => $config) {
                $isInTier = isset($tierFeatures[$key]);
                $isOverridden = isset($overrides[$key]);
                $enabled = $isOverridden ? $overrides[$key] : $isInTier;
                
                $toggleData[$category][$key] = [
                    'name' => $config['name'],
                    'default_tier' => $config['tier'],
                    'in_tier' => $isInTier,
                    'overridden' => $isOverridden,
                    'enabled' => $enabled,
                    'is_beta' => $config['is_beta'] ?? false,
                ];
            }
        }
        
        return [
            'license_key' => $licenseKey,
            'tier' => $tier,
            'categories' => $toggleData,
        ];
    }
}

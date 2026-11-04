<?php

namespace SSEOAIClient;

/**
 * International SEO Manager
 * 
 * Provides comprehensive international SEO features:
 * - International keyword research (keyword variations per country)
 * - Multi-country rank tracking
 * - Geo-specific SERP analysis
 * - Currency/language per page optimization
 * - International link building suggestions
 * - Country-specific content recommendations
 */
class InternationalSEO
{
    private Settings $settings;
    private LLMClient $llm;
    private DashboardAPI $dashboardAPI;
    
    public function __construct(Settings $settings, LLMClient $llm, DashboardAPI $dashboardAPI)
    {
        $this->settings = $settings;
        $this->llm = $llm;
        $this->dashboardAPI = $dashboardAPI;
    }
    
    public function register(): void
    {
        // Menu registration moved to Client class
        // Meta box moved to PostMetaBox tabbed container
        add_action('save_post', [$this, 'saveMetaBox'], 10, 2);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('admin_init', [$this, 'registerSettings']);
    }
    
    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('International SEO', 'ai-seo-client'),
            __('International SEO', 'ai-seo-client'),
            'manage_options',
            'ai-seo-international',
            [$this, 'renderDashboard']
        );
    }
    
    /**
     * Register settings
     */
    public function registerSettings(): void
    {
        register_setting('sseo_ai_international', 'sseo_ai_target_countries', ['default' => []]);
        register_setting('sseo_ai_international', 'sseo_ai_target_languages', ['default' => []]);
        register_setting('sseo_ai_international', 'sseo_ai_default_currency', ['default' => 'USD']);
        register_setting('sseo_ai_international', 'sseo_ai_hreflang_strategy', ['default' => 'auto']);
    }
    
    /**
     * Render international SEO dashboard
     */
    public function renderDashboard(): void
    {
        $targetCountries = get_option('sseo_ai_target_countries', []);
        $targetLanguages = get_option('sseo_ai_target_languages', []);
        
        ?>
        <div class="wrap fyn-animate-in" style="max-width:1440px;margin:0 auto;padding:28px 32px 48px;">

            <div class="fyn-page-header">
                <div class="fyn-page-header-left">
                    <div class="fyn-breadcrumb">Fyndable &rsaquo; <span class="fyn-crumb"><?php esc_html_e('International SEO', 'ai-seo-client'); ?></span></div>
                    <h1><?php esc_html_e('International SEO Management', 'ai-seo-client'); ?></h1>
                    <p class="fyn-page-desc"><?php esc_html_e('Manage target markets, track rankings per country and find international keyword variations.', 'ai-seo-client'); ?></p>
                </div>
            </div>

            <!-- Target Markets Configuration -->
            <div class="fyn-card" style="margin-bottom:20px;">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Target Markets', 'ai-seo-client'); ?></h3>
                </div>
                <form method="post" action="options.php">
                    <?php settings_fields('sseo_ai_international'); ?>
                    <div style="margin-bottom:16px;">
                        <label style="font-size:13px;font-weight:700;color:var(--text-h);display:block;margin-bottom:8px;"><?php esc_html_e('Target Countries', 'ai-seo-client'); ?></label>
                        <div style="display:flex;flex-wrap:wrap;gap:4px 0;">
                            <?php
                            $countries = $this->getCountryList();
                            foreach ($countries as $code => $name):
                            ?>
                            <label style="display:inline-block;width:200px;margin:4px 0;font-size:13px;color:var(--text-b);">
                                <input type="checkbox" name="sseo_ai_target_countries[]"
                                       value="<?php echo esc_attr($code); ?>"
                                       <?php checked(in_array($code, $targetCountries)); ?>>
                                <?php echo esc_html($name); ?> (<?php echo esc_html($code); ?>)
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="font-size:13px;font-weight:700;color:var(--text-h);display:block;margin-bottom:8px;"><?php esc_html_e('Target Languages', 'ai-seo-client'); ?></label>
                        <div style="display:flex;flex-wrap:wrap;gap:4px 0;">
                            <?php
                            $languages = $this->getLanguageList();
                            foreach ($languages as $code => $name):
                            ?>
                            <label style="display:inline-block;width:200px;margin:4px 0;font-size:13px;color:var(--text-b);">
                                <input type="checkbox" name="sseo_ai_target_languages[]"
                                       value="<?php echo esc_attr($code); ?>"
                                       <?php checked(in_array($code, $targetLanguages)); ?>>
                                <?php echo esc_html($name); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div style="margin-bottom:16px;">
                        <label for="default_currency" style="font-size:13px;font-weight:700;color:var(--text-h);display:block;margin-bottom:6px;"><?php esc_html_e('Default Currency', 'ai-seo-client'); ?></label>
                        <select id="default_currency" name="sseo_ai_default_currency" class="fyn-input" style="width:auto;">
                            <?php
                            $currencies = ['USD' => 'US Dollar', 'EUR' => 'Euro', 'GBP' => 'British Pound', 'JPY' => 'Japanese Yen'];
                            foreach ($currencies as $code => $name):
                            ?>
                            <option value="<?php echo esc_attr($code); ?>"
                                    <?php selected(get_option('sseo_ai_default_currency'), $code); ?>>
                                <?php echo esc_html($name); ?> (<?php echo esc_html($code); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php submit_button(__('Save Target Markets', 'ai-seo-client'), 'fyn-btn fyn-btn-primary'); ?>
                </form>
            </div>

            <!-- Multi-Country Rank Tracking -->
            <div class="fyn-card" style="margin-bottom:20px;">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-flag"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Multi-Country Rank Tracking', 'ai-seo-client'); ?></h3>
                </div>
                <?php if (empty($targetCountries)): ?>
                <p style="color:var(--text-m);"><?php esc_html_e('Configure target countries above to start tracking rankings.', 'ai-seo-client'); ?></p>
                <?php else: ?>
                <div style="display:flex;gap:10px;align-items:flex-end;margin-bottom:16px;">
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <label for="keyword-to-track" style="font-size:12px;font-weight:600;color:var(--text-m);"><?php esc_html_e('Track Keyword Across Countries:', 'ai-seo-client'); ?></label>
                        <input type="text" id="keyword-to-track" class="fyn-input" style="min-width:260px;"
                               placeholder="<?php esc_attr_e('Enter keyword', 'ai-seo-client'); ?>">
                    </div>
                    <button type="button" class="fyn-btn fyn-btn-primary" onclick="sseoTrackInternationalKeyword()">
                        <?php esc_html_e('Track', 'ai-seo-client'); ?>
                    </button>
                </div>
                <?php
                $internationalRankings = $this->getInternationalRankings();
                if (!empty($internationalRankings)):
                ?>
                <table class="fyn-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Keyword', 'ai-seo-client'); ?></th>
                            <?php foreach ($targetCountries as $country): ?>
                            <th><?php echo esc_html($country); ?></th>
                            <?php endforeach; ?>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($internationalRankings as $keyword => $rankings): ?>
                        <tr>
                            <td><strong><?php echo esc_html($keyword); ?></strong></td>
                            <?php foreach ($targetCountries as $country): ?>
                            <td>
                                <?php
                                $rank = $rankings[$country] ?? 'N/A';
                                $color = is_numeric($rank) && $rank <= 10 ? 'var(--fyn-success)' : 'var(--text-m)';
                                ?>
                                <span style="color:<?php echo $color; ?>;font-weight:600;">
                                    <?php echo esc_html($rank); ?>
                                </span>
                            </td>
                            <?php endforeach; ?>
                            <td>
                                <button type="button" class="fyn-btn fyn-btn-outline" style="font-size:12px;padding:4px 10px;"
                                        onclick="sseoViewGeoSERP('<?php echo esc_js($keyword); ?>')">
                                    <?php esc_html_e('View SERP', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- International Keyword Research -->
            <div class="fyn-card" style="margin-bottom:20px;">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-search"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('International Keyword Research', 'ai-seo-client'); ?></h3>
                </div>
                <div style="display:flex;gap:10px;align-items:flex-end;margin-bottom:16px;">
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <label for="base-keyword" style="font-size:12px;font-weight:600;color:var(--text-m);"><?php esc_html_e('Base Keyword:', 'ai-seo-client'); ?></label>
                        <input type="text" id="base-keyword" class="fyn-input" style="min-width:260px;"
                               placeholder="<?php esc_attr_e('e.g., running shoes', 'ai-seo-client'); ?>">
                    </div>
                    <button type="button" class="fyn-btn fyn-btn-primary" onclick="sseoResearchInternationalKeywords()">
                        <?php esc_html_e('Research Variations', 'ai-seo-client'); ?>
                    </button>
                </div>
                <div id="keyword-variations-results"></div>
            </div>

            <!-- International Link Building -->
            <div class="fyn-card">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-admin-links"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('International Link Building Suggestions', 'ai-seo-client'); ?></h3>
                </div>
                <?php
                $linkSuggestions = $this->getInternationalLinkSuggestions();
                if (empty($linkSuggestions)):
                ?>
                <p style="color:var(--text-m);"><?php esc_html_e('No link building suggestions available yet.', 'ai-seo-client'); ?></p>
                <?php else: ?>
                <table class="fyn-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Country', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Domain', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Type', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('DA', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Opportunity', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linkSuggestions as $suggestion): ?>
                        <tr>
                            <td><?php echo esc_html($suggestion['country']); ?></td>
                            <td><?php echo esc_html($suggestion['domain']); ?></td>
                            <td><?php echo esc_html($suggestion['type']); ?></td>
                            <td><?php echo esc_html($suggestion['da']); ?></td>
                            <td><?php echo esc_html($suggestion['opportunity']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        function sseoTrackInternationalKeyword() {
            const keyword = jQuery('#keyword-to-track').val().trim();
            if (!keyword) {
                alert('<?php esc_html_e('Please enter a keyword', 'ai-seo-client'); ?>');
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_track_international_keyword',
                keyword: keyword,
                nonce: '<?php echo wp_create_nonce('sseo_international'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error tracking keyword');
                }
            });
        }
        
        function sseoResearchInternationalKeywords() {
            const keyword = jQuery('#base-keyword').val().trim();
            if (!keyword) {
                alert('<?php esc_html_e('Please enter a keyword', 'ai-seo-client'); ?>');
                return;
            }
            
            jQuery('#keyword-variations-results').html('<p>Loading...</p>');
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_research_international_keywords',
                keyword: keyword,
                nonce: '<?php echo wp_create_nonce('sseo_international'); ?>'
            }, function(response) {
                if (response.success) {
                    let html = '<table class="fyn-table"><thead><tr>';
                    html += '<th><?php esc_html_e('Country', 'ai-seo-client'); ?></th>';
                    html += '<th><?php esc_html_e('Keyword Variation', 'ai-seo-client'); ?></th>';
                    html += '<th><?php esc_html_e('Search Volume', 'ai-seo-client'); ?></th>';
                    html += '<th><?php esc_html_e('Difficulty', 'ai-seo-client'); ?></th>';
                    html += '</tr></thead><tbody>';
                    
                    response.data.variations.forEach(function(v) {
                        html += '<tr>';
                        html += '<td>' + v.country + '</td>';
                        html += '<td><strong>' + v.keyword + '</strong></td>';
                        html += '<td>' + v.volume + '</td>';
                        html += '<td>' + v.difficulty + '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    jQuery('#keyword-variations-results').html(html);
                } else {
                    jQuery('#keyword-variations-results').html('<p>Error: ' + (response.data.message || 'Unknown error') + '</p>');
                }
            });
        }
        
        function sseoViewGeoSERP(keyword) {
            window.open('<?php echo admin_url('admin.php?page=ai-seo-serp-analysis&keyword='); ?>' + encodeURIComponent(keyword), '_blank');
        }
        </script>
        <?php
    }
    
    /**
     * Add international SEO meta box
     */
    public function addMetaBox(): void
    {
        $postTypes = get_post_types(['public' => true], 'names');
        
        foreach ($postTypes as $postType) {
            add_meta_box(
                'sseo-international',
                __('International SEO', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $postType,
                'normal',
                'default'
            );
        }
    }
    
    /**
     * Render international SEO meta box
     */
    public function renderMetaBox(\WP_Post $post): void
    {
        $targetCountry = get_post_meta($post->ID, '_sseo_ai_target_country', true);
        $targetLanguage = get_post_meta($post->ID, '_sseo_ai_target_language', true);
        $currency = get_post_meta($post->ID, '_sseo_ai_currency', true);
        $geoKeywords = get_post_meta($post->ID, '_sseo_ai_geo_keywords', true) ?: [];
        
        wp_nonce_field('sseo_international_save', 'sseo_international_nonce');
        ?>
        <div class="sseo-international-box">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="target_country"><?php esc_html_e('Target Country', 'ai-seo-client'); ?></label>
                    </th>
                    <td>
                        <select id="target_country" name="sseo_target_country" style="width: 100%; max-width: 300px;">
                            <option value=""><?php esc_html_e('Default (All)', 'ai-seo-client'); ?></option>
                            <?php
                            $countries = $this->getCountryList();
                            foreach ($countries as $code => $name):
                            ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($targetCountry, $code); ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Primary country this content targets', 'ai-seo-client'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="target_language"><?php esc_html_e('Target Language', 'ai-seo-client'); ?></label>
                    </th>
                    <td>
                        <select id="target_language" name="sseo_target_language" style="width: 100%; max-width: 300px;">
                            <option value=""><?php esc_html_e('Default', 'ai-seo-client'); ?></option>
                            <?php
                            $languages = $this->getLanguageList();
                            foreach ($languages as $code => $name):
                            ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($targetLanguage, $code); ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="currency"><?php esc_html_e('Currency', 'ai-seo-client'); ?></label>
                    </th>
                    <td>
                        <select id="currency" name="sseo_currency" style="width: 100%; max-width: 300px;">
                            <option value=""><?php esc_html_e('Default', 'ai-seo-client'); ?></option>
                            <option value="USD" <?php selected($currency, 'USD'); ?>>USD - US Dollar</option>
                            <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR - Euro</option>
                            <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP - British Pound</option>
                            <option value="JPY" <?php selected($currency, 'JPY'); ?>>JPY - Japanese Yen</option>
                            <option value="CAD" <?php selected($currency, 'CAD'); ?>>CAD - Canadian Dollar</option>
                            <option value="AUD" <?php selected($currency, 'AUD'); ?>>AUD - Australian Dollar</option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Currency for prices mentioned in this content', 'ai-seo-client'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Geo-Specific Keywords', 'ai-seo-client'); ?></label>
                    </th>
                    <td>
                        <div id="geo-keywords-list">
                            <?php foreach ($geoKeywords as $index => $geoKw): ?>
                            <div style="margin: 5px 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #2271b1;">
                                <input type="text" name="sseo_geo_keywords[<?php echo $index; ?>][country]" 
                                       value="<?php echo esc_attr($geoKw['country']); ?>" 
                                       placeholder="<?php esc_attr_e('Country', 'ai-seo-client'); ?>" 
                                       style="width: 100px; margin-right: 10px;">
                                <input type="text" name="sseo_geo_keywords[<?php echo $index; ?>][keyword]" 
                                       value="<?php echo esc_attr($geoKw['keyword']); ?>" 
                                       placeholder="<?php esc_attr_e('Keyword', 'ai-seo-client'); ?>" 
                                       style="width: 300px;">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button" onclick="sseoAddGeoKeyword()">
                            <?php esc_html_e('Add Geo-Specific Keyword', 'ai-seo-client'); ?>
                        </button>
                        <button type="button" class="button" onclick="sseoGenerateGeoKeywords(<?php echo $post->ID; ?>)">
                            <?php esc_html_e('AI Generate', 'ai-seo-client'); ?>
                        </button>
                    </td>
                </tr>
            </table>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                <h4><?php esc_html_e('Country-Specific Content Recommendations', 'ai-seo-client'); ?></h4>
                <button type="button" class="button button-primary" onclick="sseoGetContentRecommendations(<?php echo $post->ID; ?>)">
                    <?php esc_html_e('Get AI Recommendations', 'ai-seo-client'); ?>
                </button>
                <div id="content-recommendations" style="margin-top: 15px;"></div>
            </div>
        </div>
        
        <script>
        let geoKeywordIndex = <?php echo count($geoKeywords); ?>;
        
        function sseoAddGeoKeyword() {
            const container = document.getElementById('geo-keywords-list');
            const div = document.createElement('div');
            div.style.cssText = 'margin: 5px 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #2271b1;';
            div.innerHTML = `
                <input type="text" name="sseo_geo_keywords[${geoKeywordIndex}][country]" 
                       placeholder="<?php esc_attr_e('Country', 'ai-seo-client'); ?>" 
                       style="width: 100px; margin-right: 10px;">
                <input type="text" name="sseo_geo_keywords[${geoKeywordIndex}][keyword]" 
                       placeholder="<?php esc_attr_e('Keyword', 'ai-seo-client'); ?>" 
                       style="width: 300px;">
            `;
            container.appendChild(div);
            geoKeywordIndex++;
        }
        
        function sseoGenerateGeoKeywords(postId) {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_generate_geo_keywords',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('sseo_international'); ?>'
            }, function(response) {
                if (response.success) {
                    const container = document.getElementById('geo-keywords-list');
                    container.innerHTML = '';
                    
                    response.data.keywords.forEach(function(kw, index) {
                        const div = document.createElement('div');
                        div.style.cssText = 'margin: 5px 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #2271b1;';
                        div.innerHTML = `
                            <input type="text" name="sseo_geo_keywords[${index}][country]" 
                                   value="${kw.country}" style="width: 100px; margin-right: 10px;">
                            <input type="text" name="sseo_geo_keywords[${index}][keyword]" 
                                   value="${kw.keyword}" style="width: 300px;">
                        `;
                        container.appendChild(div);
                    });
                    
                    geoKeywordIndex = response.data.keywords.length;
                    alert('<?php esc_html_e('Geo-specific keywords generated!', 'ai-seo-client'); ?>');
                }
            });
        }
        
        function sseoGetContentRecommendations(postId) {
            jQuery('#content-recommendations').html('<p>Loading...</p>');
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_get_content_recommendations',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('sseo_international'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#content-recommendations').html(response.data.recommendations);
                } else {
                    jQuery('#content-recommendations').html('<p>Error loading recommendations</p>');
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Save international SEO meta box
     */
    public function saveMetaBox(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['sseo_international_nonce']) || !wp_verify_nonce($_POST['sseo_international_nonce'], 'sseo_international_save')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $postId)) {
            return;
        }
        
        // Save target country
        if (isset($_POST['sseo_target_country'])) {
            update_post_meta($postId, '_sseo_ai_target_country', sanitize_text_field($_POST['sseo_target_country']));
        }
        
        // Save target language
        if (isset($_POST['sseo_target_language'])) {
            update_post_meta($postId, '_sseo_ai_target_language', sanitize_text_field($_POST['sseo_target_language']));
        }
        
        // Save currency
        if (isset($_POST['sseo_currency'])) {
            update_post_meta($postId, '_sseo_ai_currency', sanitize_text_field($_POST['sseo_currency']));
        }
        
        // Save geo-specific keywords
        if (isset($_POST['sseo_geo_keywords'])) {
            $geoKeywords = [];
            foreach ($_POST['sseo_geo_keywords'] as $geoKw) {
                if (!empty($geoKw['country']) && !empty($geoKw['keyword'])) {
                    $geoKeywords[] = [
                        'country' => sanitize_text_field($geoKw['country']),
                        'keyword' => sanitize_text_field($geoKw['keyword']),
                    ];
                }
            }
            update_post_meta($postId, '_sseo_ai_geo_keywords', $geoKeywords);
        }
    }
    
    /**
     * Get country list
     */
    private function getCountryList(): array
    {
        return [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'JP' => 'Japan',
            'CN' => 'China',
            'IN' => 'India',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
        ];
    }
    
    /**
     * Get language list
     */
    private function getLanguageList(): array
    {
        return [
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'nl' => 'Dutch',
            'pt' => 'Portuguese',
            'ja' => 'Japanese',
            'zh' => 'Chinese',
            'hi' => 'Hindi',
        ];
    }
    
    /**
     * Get international rankings
     */
    private function getInternationalRankings(): array
    {
        return get_option('sseo_ai_international_rankings', []);
    }
    
    /**
     * Research international keyword variations
     */
    public function researchInternationalKeywords(string $baseKeyword, array $countries): array
    {
        $variations = [];
        
        foreach ($countries as $country) {
            // Use SERP API or AI to find keyword variations
            $countryVariations = $this->getKeywordVariationsForCountry($baseKeyword, $country);
            $variations = array_merge($variations, $countryVariations);
        }
        
        return $variations;
    }
    
    /**
     * Get keyword variations for specific country
     */
    private function getKeywordVariationsForCountry(string $keyword, string $country): array
    {
        // Use AI to generate country-specific variations
        $prompt = "Generate keyword variations for '{$keyword}' specifically for {$country}.

Consider:
- Local terminology and spelling differences
- Cultural preferences
- Search behavior patterns in {$country}
- Common synonyms used in {$country}

Provide 5-10 keyword variations in JSON format:
[
  {\"keyword\": \"...\", \"volume\": estimated_volume, \"difficulty\": \"easy/medium/hard\"}
]";
        
        $response = $this->llm->generateText($prompt, ['max_tokens' => 500, 'use_case' => 'keyword_research']);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $json = preg_replace('/```json\s*|\s*```/', '', $response);
        $data = json_decode($json, true);
        
        if (!$data) {
            return [];
        }
        
        // Add country to each variation
        foreach ($data as &$variation) {
            $variation['country'] = $country;
        }
        
        return $data;
    }
    
    /**
     * Get international link building suggestions
     */
    private function getInternationalLinkSuggestions(): array
    {
        // This would analyze backlink opportunities per country
        // Placeholder implementation
        return [];
    }
    
    /**
     * Get geo-specific SERP analysis
     */
    public function getGeoSERP(string $keyword, string $country): array
    {
        // Use SERP API with country parameter
        $serpData = $this->dashboardAPI->makeRequest('/serp/analyze', [
            'keyword' => $keyword,
            'country' => $country,
            'limit' => 10,
        ]);
        
        if (is_wp_error($serpData)) {
            return [];
        }
        
        return $serpData['results'] ?? [];
    }
    
    /**
     * Generate country-specific content recommendations
     */
    public function generateContentRecommendations(int $postId, string $country): string
    {
        $post = get_post($postId);
        $content = wp_strip_all_tags($post->post_content);
        $excerpt = mb_substr($content, 0, 500);
        
        $prompt = "Analyze this content and provide country-specific recommendations for {$country}:

Content excerpt: {$excerpt}

Provide recommendations for:
1. Cultural adaptations needed
2. Local terminology to use
3. Currency/measurement conversions
4. Local examples or references to add
5. Compliance/legal considerations for {$country}

Format as HTML list.";
        
        $recommendations = $this->llm->generateText($prompt, ['max_tokens' => 400, 'use_case' => 'content_analysis']);
        
        if (is_wp_error($recommendations)) {
            return '<p>Error generating recommendations</p>';
        }
        
        return $recommendations;
    }
    
    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/international/keywords', [
            'methods' => 'POST',
            'callback' => [$this, 'restResearchKeywords'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
        
        register_rest_route('sseo-ai/v1', '/international/track', [
            'methods' => 'POST',
            'callback' => [$this, 'restTrackKeyword'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
    }
    
    /**
     * REST: Research international keywords
     */
    public function restResearchKeywords(\WP_REST_Request $request): array
    {
        $keyword = $request->get_param('keyword');
        $countries = get_option('sseo_ai_target_countries', []);
        
        $variations = $this->researchInternationalKeywords($keyword, $countries);
        
        return ['variations' => $variations];
    }
    
    /**
     * REST: Track international keyword
     */
    public function restTrackKeyword(\WP_REST_Request $request): array
    {
        $keyword = $request->get_param('keyword');
        $countries = get_option('sseo_ai_target_countries', []);
        
        $rankings = [];
        foreach ($countries as $country) {
            $serp = $this->getGeoSERP($keyword, $country);
            $rankings[$country] = $this->findRankInSERP($serp, get_site_url());
        }
        
        // Store rankings
        $allRankings = get_option('sseo_ai_international_rankings', []);
        $allRankings[$keyword] = $rankings;
        update_option('sseo_ai_international_rankings', $allRankings);
        
        return ['success' => true, 'rankings' => $rankings];
    }
    
    /**
     * Find rank in SERP results
     */
    private function findRankInSERP(array $serp, string $siteUrl): string
    {
        $domain = parse_url($siteUrl, PHP_URL_HOST);
        
        foreach ($serp as $index => $result) {
            $resultDomain = parse_url($result['url'] ?? '', PHP_URL_HOST);
            if ($resultDomain === $domain) {
                return (string)($index + 1);
            }
        }
        
        return 'N/A';
    }
}

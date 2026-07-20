<?php

namespace SSEOAIClient;

/**
 * E-E-A-T & Expertise Validation
 * 
 * Validates content against Google's E-E-A-T guidelines:
 * - Experience
 * - Expertise
 * - Authoritativeness
 * - Trustworthiness
 */
class EEATValidator
{
    private Settings $settings;
    private LLMClient $llm;
    
    public function __construct(Settings $settings, LLMClient $llm)
    {
        $this->settings = $settings;
        $this->llm = $llm;
    }
    
    public function register(): void
    {
        // Menu registration moved to Client class
        // Meta box moved to PostMetaBox tabbed container
        add_action('save_post', [$this, 'saveMetaBox'], 10, 2);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        
        // User profile fields
        add_action('show_user_profile', [$this, 'addAuthorExpertiseFields']);
        add_action('edit_user_profile', [$this, 'addAuthorExpertiseFields']);
        add_action('personal_options_update', [$this, 'saveAuthorExpertiseFields']);
        add_action('edit_user_profile_update', [$this, 'saveAuthorExpertiseFields']);
    }
    
    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('E-E-A-T Validator', 'ai-seo-client'),
            __('E-E-A-T', 'ai-seo-client'),
            'edit_posts',
            'ai-seo-eeat',
            [$this, 'renderDashboard']
        );
    }
    
    /**
     * Render E-E-A-T dashboard
     */
    public function renderDashboard(): void
    {
        $siteEEAT = $this->analyzeSiteEEAT();
        $authorScores = $this->getAuthorScores();
        $trustSignals = $this->detectTrustSignals();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('E-E-A-T Validator', 'ai-seo-client'); ?></h1>
            <p><?php esc_html_e('Validate your content against Google\'s Experience, Expertise, Authoritativeness, and Trustworthiness guidelines.', 'ai-seo-client'); ?></p>
            
            <!-- Site-wide E-E-A-T Score -->
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Site E-E-A-T Score', 'ai-seo-client'); ?></h2>
                
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 15px;">
                    <div style="text-align: center; padding: 20px; background: #e7f3ff; border-radius: 4px;">
                        <div style="font-size: 48px; font-weight: bold; color: <?php echo $this->getScoreColor($siteEEAT['experience']); ?>;">
                            <?php echo esc_html($siteEEAT['experience']); ?>
                        </div>
                        <div style="font-weight: bold; margin-top: 10px;"><?php esc_html_e('Experience', 'ai-seo-client'); ?></div>
                        <div style="font-size: 12px; color: #666; margin-top: 5px;">
                            <?php echo esc_html($siteEEAT['experience_note']); ?>
                        </div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #d1ecf1; border-radius: 4px;">
                        <div style="font-size: 48px; font-weight: bold; color: <?php echo $this->getScoreColor($siteEEAT['expertise']); ?>;">
                            <?php echo esc_html($siteEEAT['expertise']); ?>
                        </div>
                        <div style="font-weight: bold; margin-top: 10px;"><?php esc_html_e('Expertise', 'ai-seo-client'); ?></div>
                        <div style="font-size: 12px; color: #666; margin-top: 5px;">
                            <?php echo esc_html($siteEEAT['expertise_note']); ?>
                        </div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #d1e7dd; border-radius: 4px;">
                        <div style="font-size: 48px; font-weight: bold; color: <?php echo $this->getScoreColor($siteEEAT['authoritativeness']); ?>;">
                            <?php echo esc_html($siteEEAT['authoritativeness']); ?>
                        </div>
                        <div style="font-weight: bold; margin-top: 10px;"><?php esc_html_e('Authoritativeness', 'ai-seo-client'); ?></div>
                        <div style="font-size: 12px; color: #666; margin-top: 5px;">
                            <?php echo esc_html($siteEEAT['authoritativeness_note']); ?>
                        </div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #fff3cd; border-radius: 4px;">
                        <div style="font-size: 48px; font-weight: bold; color: <?php echo $this->getScoreColor($siteEEAT['trustworthiness']); ?>;">
                            <?php echo esc_html($siteEEAT['trustworthiness']); ?>
                        </div>
                        <div style="font-weight: bold; margin-top: 10px;"><?php esc_html_e('Trustworthiness', 'ai-seo-client'); ?></div>
                        <div style="font-size: 12px; color: #666; margin-top: 5px;">
                            <?php echo esc_html($siteEEAT['trustworthiness_note']); ?>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1;">
                    <h4 style="margin-top: 0;"><?php esc_html_e('Overall Assessment', 'ai-seo-client'); ?></h4>
                    <p><?php echo wp_kses_post($siteEEAT['overall_assessment']); ?></p>
                </div>
            </div>
            
            <!-- Author Expertise Scores -->
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Author Expertise', 'ai-seo-client'); ?></h2>
                
                <?php if (!empty($authorScores)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Author', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Bio Completeness', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Credentials', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Published Posts', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('E-E-A-T Score', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($authorScores as $author): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($author['name']); ?></strong>
                            </td>
                            <td>
                                <span style="color: <?php echo $this->getScoreColor($author['bio_completeness']); ?>;">
                                    <?php echo esc_html($author['bio_completeness']); ?>%
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($author['credentials'])): ?>
                                <span style="color: #00a32a;">✓ <?php echo esc_html(count($author['credentials'])); ?> credentials</span>
                                <?php else: ?>
                                <span style="color: #d63638;">✗ No credentials</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($author['post_count']); ?></td>
                            <td>
                                <strong style="color: <?php echo $this->getScoreColor($author['eeat_score']); ?>;">
                                    <?php echo esc_html($author['eeat_score']); ?>/100
                                </strong>
                            </td>
                            <td>
                                <a href="<?php echo get_edit_user_link($author['id']); ?>" class="button button-small">
                                    <?php esc_html_e('Edit Profile', 'ai-seo-client'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p><?php esc_html_e('No authors found.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Trust Signals -->
            <div class="card">
                <h2><?php esc_html_e('Trust Signals Detected', 'ai-seo-client'); ?></h2>
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                    <div>
                        <h3><?php esc_html_e('Present Signals', 'ai-seo-client'); ?></h3>
                        <ul style="list-style: none; padding: 0;">
                            <?php foreach ($trustSignals['present'] as $signal): ?>
                            <li style="padding: 8px; margin: 5px 0; background: #d1e7dd; border-left: 3px solid #00a32a;">
                                ✓ <?php echo esc_html($signal); ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div>
                        <h3><?php esc_html_e('Missing Signals', 'ai-seo-client'); ?></h3>
                        <ul style="list-style: none; padding: 0;">
                            <?php foreach ($trustSignals['missing'] as $signal): ?>
                            <li style="padding: 8px; margin: 5px 0; background: #f8d7da; border-left: 3px solid #d63638;">
                                ✗ <?php echo esc_html($signal); ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <h3><?php esc_html_e('Recommendations', 'ai-seo-client'); ?></h3>
                    <ul>
                        <?php foreach ($trustSignals['recommendations'] as $rec): ?>
                        <li><?php echo esc_html($rec); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add E-E-A-T meta box
     */
    public function addMetaBox(): void
    {
        $postTypes = get_post_types(['public' => true], 'names');
        
        foreach ($postTypes as $postType) {
            add_meta_box(
                'sseo-eeat',
                __('E-E-A-T Score', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $postType,
                'side',
                'high'
            );
        }
    }
    
    /**
     * Render E-E-A-T meta box
     */
    public function renderMetaBox(\WP_Post $post): void
    {
        $eeatScore = $this->analyzePostEEAT($post);
        
        wp_nonce_field('sseo_eeat_save', 'sseo_eeat_nonce');
        ?>
        <div class="sseo-eeat-box">
            <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 4px; margin-bottom: 15px;">
                <div style="font-size: 48px; font-weight: bold; color: <?php echo $this->getScoreColor($eeatScore['overall']); ?>;">
                    <?php echo esc_html($eeatScore['overall']); ?>
                </div>
                <div style="font-weight: bold;"><?php esc_html_e('Overall E-E-A-T Score', 'ai-seo-client'); ?></div>
            </div>
            
            <div style="margin-bottom: 10px;">
                <strong><?php esc_html_e('Experience:', 'ai-seo-client'); ?></strong>
                <div style="background: #e0e0e0; height: 8px; border-radius: 4px; margin-top: 5px;">
                    <div style="background: <?php echo $this->getScoreColor($eeatScore['experience']); ?>; width: <?php echo $eeatScore['experience']; ?>%; height: 100%; border-radius: 4px;"></div>
                </div>
                <small><?php echo esc_html($eeatScore['experience']); ?>/100</small>
            </div>
            
            <div style="margin-bottom: 10px;">
                <strong><?php esc_html_e('Expertise:', 'ai-seo-client'); ?></strong>
                <div style="background: #e0e0e0; height: 8px; border-radius: 4px; margin-top: 5px;">
                    <div style="background: <?php echo $this->getScoreColor($eeatScore['expertise']); ?>; width: <?php echo $eeatScore['expertise']; ?>%; height: 100%; border-radius: 4px;"></div>
                </div>
                <small><?php echo esc_html($eeatScore['expertise']); ?>/100</small>
            </div>
            
            <div style="margin-bottom: 10px;">
                <strong><?php esc_html_e('Authoritativeness:', 'ai-seo-client'); ?></strong>
                <div style="background: #e0e0e0; height: 8px; border-radius: 4px; margin-top: 5px;">
                    <div style="background: <?php echo $this->getScoreColor($eeatScore['authoritativeness']); ?>; width: <?php echo $eeatScore['authoritativeness']; ?>%; height: 100%; border-radius: 4px;"></div>
                </div>
                <small><?php echo esc_html($eeatScore['authoritativeness']); ?>/100</small>
            </div>
            
            <div style="margin-bottom: 15px;">
                <strong><?php esc_html_e('Trustworthiness:', 'ai-seo-client'); ?></strong>
                <div style="background: #e0e0e0; height: 8px; border-radius: 4px; margin-top: 5px;">
                    <div style="background: <?php echo $this->getScoreColor($eeatScore['trustworthiness']); ?>; width: <?php echo $eeatScore['trustworthiness']; ?>%; height: 100%; border-radius: 4px;"></div>
                </div>
                <small><?php echo esc_html($eeatScore['trustworthiness']); ?>/100</small>
            </div>
            
            <div style="padding: 10px; background: #f0f6fc; border-left: 3px solid #2271b1; margin-bottom: 15px;">
                <strong><?php esc_html_e('Top Issues:', 'ai-seo-client'); ?></strong>
                <ul style="margin: 5px 0 0; padding-left: 20px; font-size: 12px;">
                    <?php foreach (array_slice($eeatScore['issues'], 0, 3) as $issue): ?>
                    <li><?php echo esc_html($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <button type="button" class="button" style="width: 100%;" onclick="sseoGetEEATRecommendations(<?php echo $post->ID; ?>)">
                <?php esc_html_e('Get AI Recommendations', 'ai-seo-client'); ?>
            </button>
            
            <div id="eeat-recommendations" style="margin-top: 15px;"></div>
        </div>
        
        <script>
        function sseoGetEEATRecommendations(postId) {
            jQuery('#eeat-recommendations').html('<p>Loading...</p>');
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_get_eeat_recommendations',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('sseo_eeat'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#eeat-recommendations').html(response.data.recommendations);
                } else {
                    jQuery('#eeat-recommendations').html('<p>Error loading recommendations</p>');
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Save E-E-A-T meta box
     */
    public function saveMetaBox(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['sseo_eeat_nonce']) || !wp_verify_nonce($_POST['sseo_eeat_nonce'], 'sseo_eeat_save')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $postId)) {
            return;
        }
        
        // Auto-calculate and store E-E-A-T score
        $eeatScore = $this->analyzePostEEAT($post);
        update_post_meta($postId, '_sseo_ai_eeat_score', $eeatScore);
    }
    
    /**
     * Add author expertise fields
     */
    public function addAuthorExpertiseFields(\WP_User $user): void
    {
        $credentials = get_user_meta($user->ID, 'sseo_ai_credentials', true) ?: [];
        $expertise_areas = get_user_meta($user->ID, 'sseo_ai_expertise_areas', true) ?: '';
        $social_profiles = get_user_meta($user->ID, 'sseo_ai_social_profiles', true) ?: [];
        
        ?>
        <h2><?php esc_html_e('E-E-A-T Expertise Profile', 'ai-seo-client'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="expertise_areas"><?php esc_html_e('Areas of Expertise', 'ai-seo-client'); ?></label></th>
                <td>
                    <textarea id="expertise_areas" name="sseo_expertise_areas" rows="3" class="large-text"><?php echo esc_textarea($expertise_areas); ?></textarea>
                    <p class="description"><?php esc_html_e('List your areas of expertise (one per line)', 'ai-seo-client'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e('Credentials', 'ai-seo-client'); ?></label></th>
                <td>
                    <div id="credentials-list">
                        <?php foreach ($credentials as $index => $credential): ?>
                        <div style="margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-left: 3px solid #2271b1;">
                            <input type="text" name="sseo_credentials[<?php echo $index; ?>][title]" 
                                   value="<?php echo esc_attr($credential['title'] ?? ''); ?>" 
                                   placeholder="<?php esc_attr_e('Credential Title', 'ai-seo-client'); ?>" 
                                   class="regular-text" style="margin-bottom: 5px;">
                            <input type="text" name="sseo_credentials[<?php echo $index; ?>][issuer]" 
                                   value="<?php echo esc_attr($credential['issuer'] ?? ''); ?>" 
                                   placeholder="<?php esc_attr_e('Issuing Organization', 'ai-seo-client'); ?>" 
                                   class="regular-text">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button" onclick="sseoAddCredential()">
                        <?php esc_html_e('Add Credential', 'ai-seo-client'); ?>
                    </button>
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e('Social Profiles', 'ai-seo-client'); ?></label></th>
                <td>
                    <p>
                        <label><?php esc_html_e('LinkedIn:', 'ai-seo-client'); ?></label><br>
                        <input type="url" name="sseo_social_profiles[linkedin]" 
                               value="<?php echo esc_url($social_profiles['linkedin'] ?? ''); ?>" 
                               class="regular-text">
                    </p>
                    <p>
                        <label><?php esc_html_e('Twitter/X:', 'ai-seo-client'); ?></label><br>
                        <input type="url" name="sseo_social_profiles[twitter]" 
                               value="<?php echo esc_url($social_profiles['twitter'] ?? ''); ?>" 
                               class="regular-text">
                    </p>
                    <p>
                        <label><?php esc_html_e('Personal Website:', 'ai-seo-client'); ?></label><br>
                        <input type="url" name="sseo_social_profiles[website]" 
                               value="<?php echo esc_url($social_profiles['website'] ?? ''); ?>" 
                               class="regular-text">
                    </p>
                </td>
            </tr>
        </table>
        
        <script>
        let credentialIndex = <?php echo count($credentials); ?>;
        
        function sseoAddCredential() {
            const container = document.getElementById('credentials-list');
            const div = document.createElement('div');
            div.style.cssText = 'margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-left: 3px solid #2271b1;';
            div.innerHTML = `
                <input type="text" name="sseo_credentials[${credentialIndex}][title]" 
                       placeholder="<?php esc_attr_e('Credential Title', 'ai-seo-client'); ?>" 
                       class="regular-text" style="margin-bottom: 5px;">
                <input type="text" name="sseo_credentials[${credentialIndex}][issuer]" 
                       placeholder="<?php esc_attr_e('Issuing Organization', 'ai-seo-client'); ?>" 
                       class="regular-text">
            `;
            container.appendChild(div);
            credentialIndex++;
        }
        </script>
        <?php
    }
    
    /**
     * Save author expertise fields
     */
    public function saveAuthorExpertiseFields(int $userId): void
    {
        if (!current_user_can('edit_user', $userId)) {
            return;
        }
        
        if (isset($_POST['sseo_expertise_areas'])) {
            update_user_meta($userId, 'sseo_ai_expertise_areas', sanitize_textarea_field($_POST['sseo_expertise_areas']));
        }
        
        if (isset($_POST['sseo_credentials'])) {
            $credentials = [];
            foreach ($_POST['sseo_credentials'] as $credential) {
                if (!empty($credential['title'])) {
                    $credentials[] = [
                        'title' => sanitize_text_field($credential['title']),
                        'issuer' => sanitize_text_field($credential['issuer'] ?? ''),
                    ];
                }
            }
            update_user_meta($userId, 'sseo_ai_credentials', $credentials);
        }
        
        if (isset($_POST['sseo_social_profiles'])) {
            $profiles = [];
            foreach ($_POST['sseo_social_profiles'] as $platform => $url) {
                if (!empty($url)) {
                    $profiles[$platform] = esc_url_raw($url);
                }
            }
            update_user_meta($userId, 'sseo_ai_social_profiles', $profiles);
        }
    }
    
    /**
     * Analyze site-wide E-E-A-T
     */
    private function analyzeSiteEEAT(): array
    {
        $experience = $this->calculateExperienceScore();
        $expertise = $this->calculateExpertiseScore();
        $authoritativeness = $this->calculateAuthoritativenessScore();
        $trustworthiness = $this->calculateTrustworthinessScore();
        
        $overall = round(($experience + $expertise + $authoritativeness + $trustworthiness) / 4);
        
        $assessment = $this->generateOverallAssessment($overall);
        
        return [
            'experience' => $experience,
            'experience_note' => $this->getScoreNote($experience),
            'expertise' => $expertise,
            'expertise_note' => $this->getScoreNote($expertise),
            'authoritativeness' => $authoritativeness,
            'authoritativeness_note' => $this->getScoreNote($authoritativeness),
            'trustworthiness' => $trustworthiness,
            'trustworthiness_note' => $this->getScoreNote($trustworthiness),
            'overall' => $overall,
            'overall_assessment' => $assessment,
        ];
    }
    
    /**
     * Calculate experience score
     */
    private function calculateExperienceScore(): int
    {
        $score = 0;
        
        // Check for first-person accounts
        global $wpdb;
        $posts = $wpdb->get_results("
            SELECT post_content
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type = 'post'
            ORDER BY post_date DESC
            LIMIT 20
        ");
        
        $firstPersonCount = 0;
        foreach ($posts as $post) {
            if (preg_match('/\b(I|my|we|our)\b/i', $post->post_content)) {
                $firstPersonCount++;
            }
        }
        
        $postCount = count($posts);
        $score += $postCount > 0 ? min(40, ($firstPersonCount / $postCount) * 100) : 0;
        
        // Check for case studies/examples
        $caseStudyCount = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type = 'post'
            AND (post_content LIKE '%case study%' OR post_content LIKE '%example%')
        ");
        
        $score += min(30, $caseStudyCount * 5);
        
        // Check for images/media
        $mediaCount = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type = 'attachment'
        ");
        
        $score += min(30, $mediaCount / 10);
        
        return min(100, (int)$score);
    }
    
    /**
     * Calculate expertise score
     */
    private function calculateExpertiseScore(): int
    {
        $score = 0;
        
        // Check author credentials
        $users = get_users(['role__in' => ['administrator', 'editor', 'author']]);
        $credentialedAuthors = 0;
        
        foreach ($users as $user) {
            $credentials = get_user_meta($user->ID, 'sseo_ai_credentials', true);
            if (!empty($credentials)) {
                $credentialedAuthors++;
            }
        }
        
        $userCount = count($users);
        $score += $userCount > 0 ? min(50, ($credentialedAuthors / $userCount) * 100) : 0;
        
        // Check for citations/references
        global $wpdb;
        $citationCount = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type = 'post'
            AND (post_content LIKE '%<a%href%' OR post_content LIKE '%source:%')
        ");
        
        $score += min(50, $citationCount * 2);
        
        return min(100, (int)$score);
    }
    
    /**
     * Calculate authoritativeness score
     */
    private function calculateAuthoritativenessScore(): int
    {
        $score = 0;
        
        // Check domain age
        $siteAge = (time() - strtotime(get_option('blog_public'))) / (365 * DAY_IN_SECONDS);
        $score += min(30, $siteAge * 10);
        
        // Check backlinks (would use actual backlink data)
        $score += 30; // Placeholder
        
        // Check social proof
        $users = get_users();
        $socialProfiles = 0;
        
        foreach ($users as $user) {
            $profiles = get_user_meta($user->ID, 'sseo_ai_social_profiles', true);
            if (!empty($profiles)) {
                $socialProfiles++;
            }
        }
        
        $userCount = count($users);
        $score += $userCount > 0 ? min(40, ($socialProfiles / $userCount) * 100) : 0;
        
        return min(100, (int)$score);
    }
    
    /**
     * Calculate trustworthiness score
     */
    private function calculateTrustworthinessScore(): int
    {
        $score = 0;
        
        // Check for HTTPS
        if (is_ssl()) {
            $score += 20;
        }
        
        // Check for privacy policy
        $privacyPage = get_privacy_policy_page_id();
        if ($privacyPage) {
            $score += 20;
        }
        
        // Check for contact information
        $contactPage = get_page_by_path('contact');
        if ($contactPage) {
            $score += 20;
        }
        
        // Check for about page
        $aboutPage = get_page_by_path('about');
        if ($aboutPage) {
            $score += 20;
        }
        
        // Check for author bios
        $users = get_users(['role__in' => ['administrator', 'editor', 'author']]);
        $biosCount = 0;
        
        foreach ($users as $user) {
            if (!empty($user->description)) {
                $biosCount++;
            }
        }
        
        $userCount = count($users);
        $score += $userCount > 0 ? min(20, ($biosCount / $userCount) * 100) : 0;
        
        return min(100, (int)$score);
    }
    
    /**
     * Analyze post E-E-A-T
     */
    private function analyzePostEEAT(\WP_Post $post): array
    {
        $experience = $this->analyzePostExperience($post);
        $expertise = $this->analyzePostExpertise($post);
        $authoritativeness = $this->analyzePostAuthoritativeness($post);
        $trustworthiness = $this->analyzePostTrustworthiness($post);
        
        $overall = round(($experience + $expertise + $authoritativeness + $trustworthiness) / 4);
        
        $issues = $this->identifyEEATIssues($post, [
            'experience' => $experience,
            'expertise' => $expertise,
            'authoritativeness' => $authoritativeness,
            'trustworthiness' => $trustworthiness,
        ]);
        
        return [
            'overall' => $overall,
            'experience' => $experience,
            'expertise' => $expertise,
            'authoritativeness' => $authoritativeness,
            'trustworthiness' => $trustworthiness,
            'issues' => $issues,
        ];
    }
    
    /**
     * Analyze post experience
     */
    private function analyzePostExperience(\WP_Post $post): int
    {
        $score = 0;
        $content = $post->post_content;
        
        // First-person perspective
        if (preg_match('/\b(I|my|we|our)\b/i', $content)) {
            $score += 30;
        }
        
        // Personal examples
        if (stripos($content, 'example') !== false || stripos($content, 'case study') !== false) {
            $score += 30;
        }
        
        // Images/media
        if (has_post_thumbnail($post->ID) || preg_match('/<img/', $content)) {
            $score += 20;
        }
        
        // Detailed content
        $wordCount = str_word_count(strip_tags($content));
        if ($wordCount > 1500) {
            $score += 20;
        }
        
        return min(100, $score);
    }
    
    /**
     * Analyze post expertise
     */
    private function analyzePostExpertise(\WP_Post $post): int
    {
        $score = 0;
        
        // Author credentials
        $author = get_userdata($post->post_author);
        $credentials = get_user_meta($post->post_author, 'sseo_ai_credentials', true);
        
        if (!empty($credentials)) {
            $score += 40;
        }
        
        // Citations/references
        $linkCount = substr_count($post->post_content, '<a href=');
        $score += min(30, $linkCount * 5);
        
        // Technical depth
        $wordCount = str_word_count(strip_tags($post->post_content));
        if ($wordCount > 2000) {
            $score += 30;
        }
        
        return min(100, $score);
    }
    
    /**
     * Analyze post authoritativeness
     */
    private function analyzePostAuthoritativeness(\WP_Post $post): int
    {
        $score = 0;
        
        // Author bio
        $author = get_userdata($post->post_author);
        if (!empty($author->description)) {
            $score += 30;
        }
        
        // External links to authoritative sources
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/', $post->post_content, $matches);
        $authoritativeDomains = ['wikipedia.org', 'gov', 'edu', 'nih.gov'];
        
        $authLinks = 0;
        foreach ($matches[1] as $url) {
            foreach ($authoritativeDomains as $domain) {
                if (stripos($url, $domain) !== false) {
                    $authLinks++;
                    break;
                }
            }
        }
        
        $score += min(40, $authLinks * 10);
        
        // Post age (older = more authoritative if updated)
        $lastModified = get_post_modified_time('U', false, $post);
        $published = get_post_time('U', false, $post);
        
        if ($lastModified > $published) {
            $score += 30;
        }
        
        return min(100, $score);
    }
    
    /**
     * Analyze post trustworthiness
     */
    private function analyzePostTrustworthiness(\WP_Post $post): int
    {
        $score = 0;
        
        // Fact-checking
        if (stripos($post->post_content, 'source') !== false || 
            stripos($post->post_content, 'study') !== false) {
            $score += 30;
        }
        
        // Date published/updated
        if (has_block('core/post-date', $post->post_content)) {
            $score += 20;
        }
        
        // Author byline
        if (has_block('core/post-author', $post->post_content)) {
            $score += 20;
        }
        
        // Comments enabled (transparency)
        if (comments_open($post->ID)) {
            $score += 15;
        }
        
        // No excessive ads/affiliate links
        $affiliateCount = substr_count(strtolower($post->post_content), 'affiliate');
        if ($affiliateCount < 3) {
            $score += 15;
        }
        
        return min(100, $score);
    }
    
    /**
     * Identify E-E-A-T issues
     */
    private function identifyEEATIssues(\WP_Post $post, array $scores): array
    {
        $issues = [];
        
        if ($scores['experience'] < 50) {
            $issues[] = 'Add more personal experience and examples';
        }
        
        if ($scores['expertise'] < 50) {
            $issues[] = 'Include more citations and authoritative sources';
        }
        
        if ($scores['authoritativeness'] < 50) {
            $issues[] = 'Add author bio and credentials';
        }
        
        if ($scores['trustworthiness'] < 50) {
            $issues[] = 'Add publication/update dates and fact-check sources';
        }
        
        // Author-specific issues
        $author = get_userdata($post->post_author);
        if (empty($author->description)) {
            $issues[] = 'Author bio is missing';
        }
        
        $credentials = get_user_meta($post->post_author, 'sseo_ai_credentials', true);
        if (empty($credentials)) {
            $issues[] = 'Author has no listed credentials';
        }
        
        return $issues;
    }
    
    /**
     * Get author scores
     */
    private function getAuthorScores(): array
    {
        $users = get_users(['role__in' => ['administrator', 'editor', 'author']]);
        $scores = [];
        
        foreach ($users as $user) {
            $bioCompleteness = $this->calculateBioCompleteness($user);
            $credentials = get_user_meta($user->ID, 'sseo_ai_credentials', true) ?: [];
            $postCount = count_user_posts($user->ID, 'post');
            
            $eeatScore = $this->calculateAuthorEEATScore($user);
            
            $scores[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'bio_completeness' => $bioCompleteness,
                'credentials' => $credentials,
                'post_count' => $postCount,
                'eeat_score' => $eeatScore,
            ];
        }
        
        return $scores;
    }
    
    /**
     * Calculate bio completeness
     */
    private function calculateBioCompleteness(\WP_User $user): int
    {
        $score = 0;
        
        if (!empty($user->description)) {
            $score += 40;
        }
        
        $credentials = get_user_meta($user->ID, 'sseo_ai_credentials', true);
        if (!empty($credentials)) {
            $score += 30;
        }
        
        $socialProfiles = get_user_meta($user->ID, 'sseo_ai_social_profiles', true);
        if (!empty($socialProfiles)) {
            $score += 30;
        }
        
        return $score;
    }
    
    /**
     * Calculate author E-E-A-T score
     */
    private function calculateAuthorEEATScore(\WP_User $user): int
    {
        $score = 0;
        
        // Bio completeness
        $score += $this->calculateBioCompleteness($user) * 0.4;
        
        // Post count
        $postCount = count_user_posts($user->ID, 'post');
        $score += min(30, $postCount * 2);
        
        // Credentials
        $credentials = get_user_meta($user->ID, 'sseo_ai_credentials', true);
        $score += min(30, count($credentials ?? []) * 10);
        
        return min(100, (int)$score);
    }
    
    /**
     * Detect trust signals
     */
    private function detectTrustSignals(): array
    {
        $present = [];
        $missing = [];
        $recommendations = [];
        
        // HTTPS
        if (is_ssl()) {
            $present[] = 'HTTPS/SSL Certificate';
        } else {
            $missing[] = 'HTTPS/SSL Certificate';
            $recommendations[] = 'Install an SSL certificate to secure your site';
        }
        
        // Privacy Policy
        if (get_privacy_policy_page_id()) {
            $present[] = 'Privacy Policy';
        } else {
            $missing[] = 'Privacy Policy';
            $recommendations[] = 'Create a privacy policy page';
        }
        
        // Contact Page
        if (get_page_by_path('contact')) {
            $present[] = 'Contact Page';
        } else {
            $missing[] = 'Contact Page';
            $recommendations[] = 'Add a contact page with multiple contact methods';
        }
        
        // About Page
        if (get_page_by_path('about')) {
            $present[] = 'About Page';
        } else {
            $missing[] = 'About Page';
            $recommendations[] = 'Create an about page explaining who you are';
        }
        
        // Author Bios
        $users = get_users(['role__in' => ['administrator', 'editor', 'author']]);
        $biosCount = 0;
        foreach ($users as $user) {
            if (!empty($user->description)) {
                $biosCount++;
            }
        }
        
        if ($biosCount > 0) {
            $present[] = 'Author Bios (' . $biosCount . ' authors)';
        } else {
            $missing[] = 'Author Bios';
            $recommendations[] = 'Add detailed bios for all authors';
        }
        
        // Terms of Service
        if (get_page_by_path('terms')) {
            $present[] = 'Terms of Service';
        } else {
            $missing[] = 'Terms of Service';
        }
        
        return [
            'present' => $present,
            'missing' => $missing,
            'recommendations' => $recommendations,
        ];
    }
    
    /**
     * Generate overall assessment
     */
    private function generateOverallAssessment(int $score): string
    {
        if ($score >= 80) {
            return 'Excellent E-E-A-T signals. Your site demonstrates strong experience, expertise, authoritativeness, and trustworthiness.';
        } elseif ($score >= 60) {
            return 'Good E-E-A-T foundation. Focus on improving author credentials and adding more trust signals.';
        } elseif ($score >= 40) {
            return 'Moderate E-E-A-T signals. Significant improvements needed in author expertise and site trustworthiness.';
        } else {
            return 'Low E-E-A-T signals. Immediate action required to establish credibility, expertise, and trust.';
        }
    }
    
    /**
     * Get score note
     */
    private function getScoreNote(int $score): string
    {
        if ($score >= 80) return 'Excellent';
        if ($score >= 60) return 'Good';
        if ($score >= 40) return 'Needs Work';
        return 'Poor';
    }
    
    /**
     * Get score color
     */
    private function getScoreColor(int $score): string
    {
        if ($score >= 80) return '#00a32a';
        if ($score >= 60) return '#dba617';
        if ($score >= 40) return '#d63638';
        return '#d63638';
    }
    
    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/eeat/post/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetPostEEAT'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
        
        register_rest_route('sseo-ai/v1', '/eeat/site', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetSiteEEAT'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
    }
    
    public function restGetPostEEAT(\WP_REST_Request $request): array
    {
        $postId = (int)$request->get_param('id');
        $post = get_post($postId);
        
        if (!$post) {
            return ['error' => 'Post not found'];
        }
        
        return ['eeat' => $this->analyzePostEEAT($post)];
    }
    
    public function restGetSiteEEAT(): array
    {
        return ['eeat' => $this->analyzeSiteEEAT()];
    }
}

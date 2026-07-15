<?php

namespace SSEOAIClient;

/**
 * White-Label / Agency Manager
 * 
 * Provides white-label and agency features:
 * - Custom branding in export reports
 * - Client portal (multi-client dashboard)
 * - Team collaboration workspace
 * - Client billing/invoicing
 * - White-label WordPress theme
 * - Custom domain support
 * - Reseller/affiliate program backend
 */
class WhiteLabelManager
{
    private Settings $settings;
    
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }
    
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        
        // Apply white-label branding
        add_filter('admin_footer_text', [$this, 'customAdminFooter']);
        add_action('admin_head', [$this, 'customAdminStyles']);
        add_action('login_head', [$this, 'customLoginStyles']);
        add_filter('login_headerurl', [$this, 'customLoginUrl']);
        add_filter('login_headertext', [$this, 'customLoginText']);
        add_filter('login_title', [$this, 'customLoginTitle'], 10, 2);
        add_filter('login_body_class', [$this, 'customLoginBodyClass']);

        // Client portal
        add_action('init', [$this, 'registerClientPortalEndpoint']);
        add_filter('template_include', [$this, 'clientPortalTemplate']);
    }
    
    public function addMenu(): void
    {
        add_options_page(
            __('White-Label & Fynable Login', 'ai-seo-client'),
            __('White-Label & Login', 'ai-seo-client'),
            'manage_options',
            'ai-seo-white-label',
            [$this, 'renderSettings']
        );
    }
    
    /**
     * Register settings
     */
    public function registerSettings(): void
    {
        // Branding
        register_setting('sseo_ai_white_label', 'sseo_ai_wl_company_name');
        register_setting('sseo_ai_white_label', 'sseo_ai_wl_company_logo');
        register_setting('sseo_ai_white_label', 'sseo_ai_wl_primary_color');
        register_setting('sseo_ai_white_label', 'sseo_ai_wl_secondary_color');
        register_setting('sseo_ai_white_label', 'sseo_ai_wl_footer_text');
        register_setting('sseo_ai_white_label', 'sseo_ai_wl_custom_domain');
        
        // Client Portal
        register_setting('sseo_ai_white_label', 'sseo_ai_wl_portal_enabled', ['default' => false]);
        register_setting('sseo_ai_white_label', 'sseo_ai_wl_portal_slug', ['default' => 'seo-portal']);
        
        // Reseller
        register_setting('sseo_ai_white_label', 'sseo_ai_wl_reseller_enabled', ['default' => false]);
        register_setting('sseo_ai_white_label', 'sseo_ai_wl_commission_rate', ['default' => 20]);

        // Fynable branded login
        register_setting('sseo_ai_white_label', 'sseo_ai_fynable_login_enabled', ['default' => false]);
        register_setting('sseo_ai_white_label', 'sseo_ai_fynable_login_title', ['default' => '']);
        register_setting('sseo_ai_white_label', 'sseo_ai_fynable_login_bg_color', ['default' => '#f0f4f8']);
        register_setting('sseo_ai_white_label', 'sseo_ai_fynable_login_bg_image', ['default' => '']);

        // Free tier toggle (disabled by default for beta)
        register_setting('sseo_ai_white_label', 'sseo_ai_free_tier_enabled', ['default' => false]);
    }
    
    /**
     * Render white-label settings page
     */
    public function renderSettings(): void
    {
        $companyName = get_option('sseo_ai_wl_company_name', '');
        $companyLogo = get_option('sseo_ai_wl_company_logo', '');
        $primaryColor = get_option('sseo_ai_wl_primary_color', '#2271b1');
        $secondaryColor = get_option('sseo_ai_wl_secondary_color', '#135e96');
        $footerText = get_option('sseo_ai_wl_footer_text', '');
        $customDomain = get_option('sseo_ai_wl_custom_domain', '');
        $fynableLoginEnabled = get_option('sseo_ai_fynable_login_enabled', false);
        $fynableLoginTitle = get_option('sseo_ai_fynable_login_title', '');
        $fynableLoginBgColor = get_option('sseo_ai_fynable_login_bg_color', '#f0f4f8');
        $fynableLoginBgImage = get_option('sseo_ai_fynable_login_bg_image', '');
        $freeTierEnabled = get_option('sseo_ai_free_tier_enabled', false);
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('White-Label Settings', 'ai-seo-client'); ?></h1>
            
            <form method="post" action="options.php" enctype="multipart/form-data">
                <?php settings_fields('sseo_ai_white_label'); ?>
                
                <div class="card" style="margin-bottom: 20px;">
                    <h2><?php esc_html_e('Branding', 'ai-seo-client'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="company_name"><?php esc_html_e('Company Name', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="company_name" name="sseo_ai_wl_company_name" 
                                       value="<?php echo esc_attr($companyName); ?>" class="regular-text">
                                <p class="description">
                                    <?php esc_html_e('This will replace the default brand name throughout the interface', 'ai-seo-client'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="company_logo"><?php esc_html_e('Company Logo', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="company_logo" name="sseo_ai_wl_company_logo" 
                                       value="<?php echo esc_attr($companyLogo); ?>" class="regular-text">
                                <button type="button" class="button" onclick="sseoUploadLogo()">
                                    <?php esc_html_e('Upload Logo', 'ai-seo-client'); ?>
                                </button>
                                <?php if ($companyLogo): ?>
                                <div style="margin-top: 10px;">
                                    <img src="<?php echo esc_url($companyLogo); ?>" style="max-width: 200px; height: auto;">
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="primary_color"><?php esc_html_e('Primary Color', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="color" id="primary_color" name="sseo_ai_wl_primary_color" 
                                       value="<?php echo esc_attr($primaryColor); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="secondary_color"><?php esc_html_e('Secondary Color', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="color" id="secondary_color" name="sseo_ai_wl_secondary_color" 
                                       value="<?php echo esc_attr($secondaryColor); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="footer_text"><?php esc_html_e('Admin Footer Text', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="footer_text" name="sseo_ai_wl_footer_text" 
                                       value="<?php echo esc_attr($footerText); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="custom_domain"><?php esc_html_e('Custom Domain', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="custom_domain" name="sseo_ai_wl_custom_domain" 
                                       value="<?php echo esc_attr($customDomain); ?>" class="regular-text" 
                                       placeholder="https://seo.youragency.com">
                                <p class="description">
                                    <?php esc_html_e('Custom domain for client portal and reports', 'ai-seo-client'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="card" style="margin-bottom: 20px;">
                    <h2><?php esc_html_e('Export Report Branding', 'ai-seo-client'); ?></h2>
                    
                    <p><?php esc_html_e('All exported reports (PDF, CSV, etc.) will include your branding:', 'ai-seo-client'); ?></p>
                    
                    <ul>
                        <li>✓ <?php esc_html_e('Company logo in header', 'ai-seo-client'); ?></li>
                        <li>✓ <?php esc_html_e('Custom color scheme', 'ai-seo-client'); ?></li>
                        <li>✓ <?php esc_html_e('Company name and contact info', 'ai-seo-client'); ?></li>
                        <li>✓ <?php esc_html_e('No "Powered by Fyndable" branding', 'ai-seo-client'); ?></li>
                    </ul>
                    
                    <button type="button" class="button" onclick="sseoPreviewReport()">
                        <?php esc_html_e('Preview Sample Report', 'ai-seo-client'); ?>
                    </button>
                </div>

                <div class="card" style="margin-bottom: 20px;">
                    <h2><?php esc_html_e('Fynable Login Screen', 'ai-seo-client'); ?></h2>
                    <p><?php esc_html_e('Customize the WordPress login page with a fully Fynable-branded design.', 'ai-seo-client'); ?></p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="fynable_login_enabled"><?php esc_html_e('Enable Fynable Login', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="fynable_login_enabled" name="sseo_ai_fynable_login_enabled" value="1" <?php checked($fynableLoginEnabled); ?>>
                                <p class="description"><?php esc_html_e('Replace the default WordPress login with a custom Fynable-branded design.', 'ai-seo-client'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="fynable_login_title"><?php esc_html_e('Login Page Title', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="fynable_login_title" name="sseo_ai_fynable_login_title"
                                       value="<?php echo esc_attr($fynableLoginTitle); ?>" class="regular-text"
                                       placeholder="<?php esc_attr_e('e.g., Fyndable Smart SEO', 'ai-seo-client'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="fynable_login_bg_color"><?php esc_html_e('Background Color', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="color" id="fynable_login_bg_color" name="sseo_ai_fynable_login_bg_color"
                                       value="<?php echo esc_attr($fynableLoginBgColor); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="fynable_login_bg_image"><?php esc_html_e('Background Image URL', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="fynable_login_bg_image" name="sseo_ai_fynable_login_bg_image"
                                       value="<?php echo esc_attr($fynableLoginBgImage); ?>" class="regular-text"
                                       placeholder="https://example.com/background.jpg">
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="card" style="margin-bottom: 20px;">
                    <h2><?php esc_html_e('Beta / Onboarding', 'ai-seo-client'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="free_tier_enabled"><?php esc_html_e('Enable Free Tier', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="free_tier_enabled" name="sseo_ai_free_tier_enabled" value="1" <?php checked($freeTierEnabled); ?>>
                                <p class="description"><?php esc_html_e('Allow users to skip the license step during onboarding. Disabled by default for the beta.', 'ai-seo-client'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(__('Save White-Label Settings', 'ai-seo-client')); ?>
            </form>
        </div>
        
        <script>
        function sseoUploadLogo() {
            const frame = wp.media({
                title: '<?php esc_html_e('Select Company Logo', 'ai-seo-client'); ?>',
                button: { text: '<?php esc_html_e('Use this logo', 'ai-seo-client'); ?>' },
                multiple: false
            });
            
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                jQuery('#company_logo').val(attachment.url);
            });
            
            frame.open();
        }
        
        function sseoPreviewReport() {
            window.open('<?php echo admin_url('admin-ajax.php?action=sseo_ai_preview_report'); ?>', '_blank');
        }
        </script>
        <?php
    }
    
    /**
     * Render client portal
     */
    public function renderClientPortal(): void
    {
        $clients = get_option('sseo_ai_clients', []);
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Client Portal Management', 'ai-seo-client'); ?></h1>
            
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Add New Client', 'ai-seo-client'); ?></h2>
                
                <form id="add-client-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="client_name"><?php esc_html_e('Client Name', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="client_name" name="client_name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="client_email"><?php esc_html_e('Email', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="email" id="client_email" name="client_email" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="client_website"><?php esc_html_e('Website', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="client_website" name="client_website" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="client_package"><?php esc_html_e('Package', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <select id="client_package" name="client_package">
                                    <option value="basic"><?php esc_html_e('Basic', 'ai-seo-client'); ?></option>
                                    <option value="professional"><?php esc_html_e('Professional', 'ai-seo-client'); ?></option>
                                    <option value="enterprise"><?php esc_html_e('Enterprise', 'ai-seo-client'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Add Client', 'ai-seo-client'); ?>
                    </button>
                </form>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e('Active Clients', 'ai-seo-client'); ?></h2>
                
                <?php if (empty($clients)): ?>
                <p><?php esc_html_e('No clients added yet.', 'ai-seo-client'); ?></p>
                <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Client Name', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Email', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Website', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Package', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Portal Access', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $clientId => $client): ?>
                        <tr>
                            <td><strong><?php echo esc_html($client['name']); ?></strong></td>
                            <td><?php echo esc_html($client['email']); ?></td>
                            <td>
                                <?php if (!empty($client['website'])): ?>
                                <a href="<?php echo esc_url($client['website']); ?>" target="_blank">
                                    <?php echo esc_html($client['website']); ?>
                                </a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(ucfirst($client['package'] ?? 'basic')); ?></td>
                            <td>
                                <a href="<?php echo home_url('/seo-portal/?client=' . $clientId); ?>" target="_blank">
                                    <?php esc_html_e('View Portal', 'ai-seo-client'); ?>
                                </a>
                            </td>
                            <td>
                                <button type="button" class="button button-small" 
                                        onclick="sseoSendPortalInvite('<?php echo esc_js($clientId); ?>')">
                                    <?php esc_html_e('Send Invite', 'ai-seo-client'); ?>
                                </button>
                                <button type="button" class="button button-small" 
                                        onclick="sseoEditClient('<?php echo esc_js($clientId); ?>')">
                                    <?php esc_html_e('Edit', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery('#add-client-form').on('submit', function(e) {
            e.preventDefault();
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_add_client',
                client_name: jQuery('#client_name').val(),
                client_email: jQuery('#client_email').val(),
                client_website: jQuery('#client_website').val(),
                client_package: jQuery('#client_package').val(),
                nonce: '<?php echo wp_create_nonce('sseo_client'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error adding client');
                }
            });
        });
        
        function sseoSendPortalInvite(clientId) {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_send_portal_invite',
                client_id: clientId,
                nonce: '<?php echo wp_create_nonce('sseo_client'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Portal invite sent!', 'ai-seo-client'); ?>');
                } else {
                    alert(response.data.message || 'Error sending invite');
                }
            });
        }
        
        function sseoEditClient(clientId) {
            // Open edit modal
            alert('Edit functionality coming soon');
        }
        </script>
        <?php
    }
    
    /**
     * Render team workspace
     */
    public function renderTeamWorkspace(): void
    {
        $teamMembers = get_option('sseo_ai_team_members', []);
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Team Collaboration Workspace', 'ai-seo-client'); ?></h1>
            
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Invite Team Member', 'ai-seo-client'); ?></h2>
                
                <form id="invite-team-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="member_email"><?php esc_html_e('Email', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="email" id="member_email" name="member_email" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="member_role"><?php esc_html_e('Role', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <select id="member_role" name="member_role">
                                    <option value="admin"><?php esc_html_e('Admin', 'ai-seo-client'); ?></option>
                                    <option value="editor"><?php esc_html_e('Editor', 'ai-seo-client'); ?></option>
                                    <option value="viewer"><?php esc_html_e('Viewer', 'ai-seo-client'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Send Invitation', 'ai-seo-client'); ?>
                    </button>
                </form>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e('Team Members', 'ai-seo-client'); ?></h2>
                
                <?php if (empty($teamMembers)): ?>
                <p><?php esc_html_e('No team members yet. Invite your first team member above.', 'ai-seo-client'); ?></p>
                <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Email', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Role', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Last Active', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teamMembers as $memberId => $member): ?>
                        <tr>
                            <td><?php echo esc_html($member['name'] ?? 'Pending'); ?></td>
                            <td><?php echo esc_html($member['email']); ?></td>
                            <td><?php echo esc_html(ucfirst($member['role'])); ?></td>
                            <td>
                                <?php 
                                $status = $member['status'] ?? 'pending';
                                $color = $status === 'active' ? '#00a32a' : '#dba617';
                                ?>
                                <span style="color: <?php echo $color; ?>;">
                                    <?php echo esc_html(ucfirst($status)); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $lastActive = $member['last_active'] ?? '';
                                echo $lastActive ? esc_html(human_time_diff(strtotime($lastActive))) . ' ago' : 'Never';
                                ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small" 
                                        onclick="sseoRemoveTeamMember('<?php echo esc_js($memberId); ?>')">
                                    <?php esc_html_e('Remove', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php esc_html_e('Activity Log', 'ai-seo-client'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Time', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('User', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Action', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="3"><?php esc_html_e('No activity yet', 'ai-seo-client'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
        jQuery('#invite-team-form').on('submit', function(e) {
            e.preventDefault();
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_invite_team_member',
                member_email: jQuery('#member_email').val(),
                member_role: jQuery('#member_role').val(),
                nonce: '<?php echo wp_create_nonce('sseo_team'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error sending invitation');
                }
            });
        });
        
        function sseoRemoveTeamMember(memberId) {
            if (!confirm('<?php esc_html_e('Remove this team member?', 'ai-seo-client'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_remove_team_member',
                member_id: memberId,
                nonce: '<?php echo wp_create_nonce('sseo_team'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Render billing page
     */
    public function renderBilling(): void
    {
        $clients = get_option('sseo_ai_clients', []);
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Client Billing & Invoicing', 'ai-seo-client'); ?></h1>
            
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Create Invoice', 'ai-seo-client'); ?></h2>
                
                <form id="create-invoice-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="invoice_client"><?php esc_html_e('Client', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <select id="invoice_client" name="invoice_client" class="regular-text" required>
                                    <option value=""><?php esc_html_e('Select Client', 'ai-seo-client'); ?></option>
                                    <?php foreach ($clients as $clientId => $client): ?>
                                    <option value="<?php echo esc_attr($clientId); ?>">
                                        <?php echo esc_html($client['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="invoice_amount"><?php esc_html_e('Amount', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="invoice_amount" name="invoice_amount" 
                                       class="regular-text" step="0.01" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="invoice_description"><?php esc_html_e('Description', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <textarea id="invoice_description" name="invoice_description" 
                                          rows="3" class="large-text"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="invoice_due_date"><?php esc_html_e('Due Date', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="date" id="invoice_due_date" name="invoice_due_date" required>
                            </td>
                        </tr>
                    </table>
                    
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Create Invoice', 'ai-seo-client'); ?>
                    </button>
                </form>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e('Recent Invoices', 'ai-seo-client'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Invoice #', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Client', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Amount', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Due Date', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6"><?php esc_html_e('No invoices yet', 'ai-seo-client'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
        jQuery('#create-invoice-form').on('submit', function(e) {
            e.preventDefault();
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_create_invoice',
                client_id: jQuery('#invoice_client').val(),
                amount: jQuery('#invoice_amount').val(),
                description: jQuery('#invoice_description').val(),
                due_date: jQuery('#invoice_due_date').val(),
                nonce: '<?php echo wp_create_nonce('sseo_billing'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Invoice created!', 'ai-seo-client'); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || 'Error creating invoice');
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Apply custom admin footer
     */
    public function customAdminFooter(string $text): string
    {
        $footerText = get_option('sseo_ai_wl_footer_text', '');
        
        if (!empty($footerText)) {
            return $footerText;
        }
        
        return $text;
    }
    
    /**
     * Apply custom admin styles
     */
    public function customAdminStyles(): void
    {
        $primaryColor = get_option('sseo_ai_wl_primary_color', '');
        $secondaryColor = get_option('sseo_ai_wl_secondary_color', '');
        
        if (empty($primaryColor)) {
            return;
        }
        
        ?>
        <style>
        .button-primary {
            background-color: <?php echo esc_attr($primaryColor); ?> !important;
            border-color: <?php echo esc_attr($secondaryColor); ?> !important;
        }
        .button-primary:hover {
            background-color: <?php echo esc_attr($secondaryColor); ?> !important;
        }
        </style>
        <?php
    }
    
    /**
     * Apply custom login styles
     */
    public function customLoginStyles(): void
    {
        if ($this->isFynableLoginEnabled()) {
            $this->renderFynableLoginStyles();
            return;
        }

        $companyLogo = get_option('sseo_ai_wl_company_logo', '');
        $primaryColor = get_option('sseo_ai_wl_primary_color', '');

        if (empty($companyLogo) && empty($primaryColor)) {
            return;
        }

        ?>
        <style>
        <?php if ($companyLogo): ?>
        #login h1 a {
            background-image: url('<?php echo esc_url($companyLogo); ?>');
            background-size: contain;
            width: 100%;
        }
        <?php endif; ?>

        <?php if ($primaryColor): ?>
        .wp-core-ui .button-primary {
            background-color: <?php echo esc_attr($primaryColor); ?>;
        }
        <?php endif; ?>
        </style>
        <?php
    }

    /**
     * Render a fully Fynable-branded WordPress login page
     */
    private function renderFynableLoginStyles(): void
    {
        $companyName = get_option('sseo_ai_wl_company_name', 'Fyndable');
        $companyLogo = get_option('sseo_ai_wl_company_logo', '');
        $primaryColor = get_option('sseo_ai_wl_primary_color', '#2271b1');
        $secondaryColor = get_option('sseo_ai_wl_secondary_color', '#135e96');
        $title = get_option('sseo_ai_fynable_login_title', $companyName . ' Smart SEO');
        $bgColor = get_option('sseo_ai_fynable_login_bg_color', '#f0f4f8');
        $bgImage = get_option('sseo_ai_fynable_login_bg_image', '');

        $logoCss = $companyLogo ? 'background-image: url(' . esc_url($companyLogo) . ') !important;' : '';
        $bgImageCss = $bgImage ? 'background-image: url(' . esc_url($bgImage) . ');' : '';

        ?>
        <style>
        body.sseo-fynable-login {
            background-color: <?php echo esc_attr($bgColor); ?>;
            <?php echo $bgImage ? 'background-size: cover; background-position: center;' : ''; ?>
            <?php echo $bgImageCss; ?>
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        body.sseo-fynable-login #login {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        body.sseo-fynable-login #login h1 a {
            <?php echo $logoCss; ?>
            background-size: contain;
            width: 100%;
            height: 64px;
            margin-bottom: 20px;
        }
        body.sseo-fynable-login #login h1 a::after {
            content: '<?php echo esc_js($title); ?>';
            display: block;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            text-indent: 0;
            margin-top: 70px;
        }
        body.sseo-fynable-login .message,
        body.sseo-fynable-login #login_error {
            border-radius: 8px;
            border-left-color: <?php echo esc_attr($primaryColor); ?>;
        }
        body.sseo-fynable-login #loginform,
        body.sseo-fynable-login #lostpasswordform,
        body.sseo-fynable-login #registerform {
            border: none;
            box-shadow: none;
            padding: 0;
        }
        body.sseo-fynable-login #loginform .input,
        body.sseo-fynable-login #lostpasswordform .input,
        body.sseo-fynable-login #registerform .input {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            font-size: 15px;
        }
        body.sseo-fynable-login #loginform .input:focus,
        body.sseo-fynable-login #lostpasswordform .input:focus,
        body.sseo-fynable-login #registerform .input:focus {
            border-color: <?php echo esc_attr($primaryColor); ?>;
            box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
        }
        body.sseo-fynable-login .wp-core-ui .button-primary {
            background-color: <?php echo esc_attr($primaryColor); ?>;
            border-color: <?php echo esc_attr($primaryColor); ?>;
            border-radius: 8px;
            padding: 12px;
            font-size: 15px;
            width: 100%;
            transition: background-color 0.2s;
        }
        body.sseo-fynable-login .wp-core-ui .button-primary:hover {
            background-color: <?php echo esc_attr($secondaryColor); ?>;
            border-color: <?php echo esc_attr($secondaryColor); ?>;
        }
        body.sseo-fynable-login #nav,
        body.sseo-fynable-login #backtoblog {
            text-align: center;
            padding: 0;
            margin-top: 16px;
        }
        body.sseo-fynable-login #nav a,
        body.sseo-fynable-login #backtoblog a {
            color: <?php echo esc_attr($primaryColor); ?>;
            text-decoration: none;
        }
        body.sseo-fynable-login #nav a:hover,
        body.sseo-fynable-login #backtoblog a:hover {
            text-decoration: underline;
        }
        </style>
        <?php
    }

    /**
     * Custom login URL
     */
    public function customLoginUrl(string $url): string
    {
        return $this->isFynableLoginEnabled() ? home_url('/') : $url;
    }

    /**
     * Custom login header text
     */
    public function customLoginText(string $text): string
    {
        if (!$this->isFynableLoginEnabled()) {
            return $text;
        }

        $companyName = get_option('sseo_ai_wl_company_name', 'Fyndable');
        return $companyName ?: 'Fyndable';
    }

    /**
     * Custom login title
     */
    public function customLoginTitle(string $title, string $sep): string
    {
        if (!$this->isFynableLoginEnabled()) {
            return $title;
        }

        $companyName = get_option('sseo_ai_wl_company_name', 'Fyndable');
        $loginTitle = get_option('sseo_ai_fynable_login_title', $companyName . ' Smart SEO');
        return $loginTitle . ' ' . $sep . ' ' . get_bloginfo('name');
    }

    /**
     * Add custom body class to login page
     */
    public function customLoginBodyClass(array $classes): array
    {
        if ($this->isFynableLoginEnabled()) {
            $classes[] = 'sseo-fynable-login';
        }
        return $classes;
    }

    /**
     * Check if Fynable login is enabled
     */
    private function isFynableLoginEnabled(): bool
    {
        return (bool) get_option('sseo_ai_fynable_login_enabled', false);
    }

    /**
     * Check if free tier is enabled
     */
    private function isFreeTierEnabled(): bool
    {
        return (bool) get_option('sseo_ai_free_tier_enabled', false);
    }
    
    /**
     * Register client portal endpoint
     */
    public function registerClientPortalEndpoint(): void
    {
        $portalEnabled = get_option('sseo_ai_wl_portal_enabled', false);
        
        if (!$portalEnabled) {
            return;
        }
        
        $slug = get_option('sseo_ai_wl_portal_slug', 'seo-portal');
        add_rewrite_rule("^{$slug}/?", 'index.php?sseo_client_portal=1', 'top');
        add_rewrite_tag('%sseo_client_portal%', '([^&]+)');
    }
    
    /**
     * Client portal template
     */
    public function clientPortalTemplate($template)
    {
        if (get_query_var('sseo_client_portal')) {
            return SSEO_AI_CLIENT_PLUGIN_DIR . 'templates/client-portal.php';
        }
        
        return $template;
    }
    
    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/white-label/preview-report', [
            'methods' => 'GET',
            'callback' => [$this, 'restPreviewReport'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
    }
    
    /**
     * REST: Preview branded report
     */
    public function restPreviewReport(): string
    {
        $companyName = get_option('sseo_ai_wl_company_name', 'Your Company');
        $companyLogo = get_option('sseo_ai_wl_company_logo', '');
        $primaryColor = get_option('sseo_ai_wl_primary_color', '#2271b1');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title><?php echo esc_html($companyName); ?> - SEO Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .header { text-align: center; margin-bottom: 40px; border-bottom: 3px solid <?php echo esc_attr($primaryColor); ?>; padding-bottom: 20px; }
                .header img { max-width: 200px; }
                .header h1 { color: <?php echo esc_attr($primaryColor); ?>; }
                .section { margin: 30px 0; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background-color: <?php echo esc_attr($primaryColor); ?>; color: white; }
            </style>
        </head>
        <body>
            <div class="header">
                <?php if ($companyLogo): ?>
                <img src="<?php echo esc_url($companyLogo); ?>" alt="<?php echo esc_attr($companyName); ?>">
                <?php endif; ?>
                <h1><?php echo esc_html($companyName); ?></h1>
                <p>SEO Performance Report - <?php echo date('F Y'); ?></p>
            </div>
            
            <div class="section">
                <h2>Executive Summary</h2>
                <p>This is a sample white-labeled report with your custom branding.</p>
            </div>
            
            <div class="section">
                <h2>Key Metrics</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Value</th>
                            <th>Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Organic Traffic</td>
                            <td>12,450</td>
                            <td style="color: green;">+15%</td>
                        </tr>
                        <tr>
                            <td>Keywords Ranking</td>
                            <td>234</td>
                            <td style="color: green;">+12</td>
                        </tr>
                        <tr>
                            <td>Average Position</td>
                            <td>8.5</td>
                            <td style="color: green;">-2.3</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

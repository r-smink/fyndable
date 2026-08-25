<?php

namespace SSEOAIClient;

class RolePermissions
{
    private array $capabilities = [
        'aiseo_manage_settings' => 'Manage AI SEO settings',
        'aiseo_view_dashboard' => 'View AI SEO dashboard',
        'aiseo_edit_seo_meta' => 'Edit SEO meta data',
        'aiseo_view_serp' => 'View SERP snapshots',
        'aiseo_manage_redirects' => 'Manage redirects',
        'aiseo_view_404s' => 'View 404 monitor',
        'aiseo_manage_schema' => 'Manage schema markup',
        'aiseo_use_ai_features' => 'Use AI features',
        'aiseo_bulk_actions' => 'Perform bulk SEO actions',
    ];

    public function register(): void
    {
        add_action('init', [$this, 'addCapabilities']);
        add_action('admin_init', [$this, 'filterAdminAccess']);
    }

    public function addCapabilities(): void
    {
        $roles = [
            'administrator' => array_keys($this->capabilities),
            'editor' => [
                'aiseo_view_dashboard',
                'aiseo_edit_seo_meta',
                'aiseo_view_serp',
                'aiseo_view_404s',
                'aiseo_use_ai_features',
                'aiseo_bulk_actions',
            ],
            'author' => [
                'aiseo_edit_seo_meta',
                'aiseo_use_ai_features',
            ],
            'contributor' => [
                'aiseo_edit_seo_meta',
            ],
        ];

        foreach ($roles as $roleName => $caps) {
            $role = get_role($roleName);
            if (!$role) {
                continue;
            }

            foreach ($caps as $cap) {
                if (!isset($this->capabilities[$cap])) {
                    continue;
                }
                $role->add_cap($cap);
            }
        }

        // Remove caps that should not be there
        foreach (['author', 'contributor'] as $roleName) {
            $role = get_role($roleName);
            if (!$role) {
                continue;
            }
            $allCaps = array_keys($this->capabilities);
            $allowed = $roles[$roleName] ?? [];
            $toRemove = array_diff($allCaps, $allowed);

            foreach ($toRemove as $cap) {
                $role->remove_cap($cap);
            }
        }
    }

    public function filterAdminAccess(): void
    {
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }

        $page = $_GET['page'] ?? '';
        if (strpos($page, 'ai-seo-client') !== 0) {
            return;
        }

        $requiredCap = $this->getRequiredCapForPage($page);

        if ($requiredCap && !current_user_can($requiredCap)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'ai-seo-client'), 403);
        }
    }

    private function getRequiredCapForPage(string $page): ?string
    {
        $map = [
            'ai-seo-client' => 'manage_options', // Connection page - all admins can access
            'ai-seo-competitor-research' => 'edit_posts',
            'ai-seo-assistant-dashboard' => 'aiseo_view_dashboard',
            'ai-seo-assistant-snapshots' => 'aiseo_view_serp',
            'ai-seo-assistant-redirects' => 'aiseo_manage_redirects',
            'ai-seo-assistant-404s' => 'aiseo_view_404s',
            'ai-seo-assistant-schema' => 'aiseo_manage_schema',
        ];

        return $map[$page] ?? 'aiseo_view_dashboard';
    }

    public function renderPermissionsSettings(): void
    {
        $roles = wp_roles()->get_names();
        ?>
        <h3><?php esc_html_e('Role-Based Permissions', 'ai-seo-client'); ?></h3>
        <p><?php esc_html_e('Configure which user roles can access AI SEO features:', 'ai-seo-client'); ?></p>

        <table class="form-table">
            <?php foreach ($this->capabilities as $cap => $label) : ?>
            <tr>
                <th><?php echo esc_html($label); ?></th>
                <td>
                    <?php foreach ($roles as $roleKey => $roleName) : 
                        $role = get_role($roleKey);
                        $hasCap = $role && $role->has_cap($cap);
                    ?>
                    <label style="margin-right:15px;">
                        <input type="checkbox" disabled <?php checked($hasCap); ?>>
                        <?php echo esc_html(translate_user_role($roleName)); ?>
                    </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <p class="description">
            <?php esc_html_e('Note: Administrators have all permissions. Permissions can be customized with a roles plugin.', 'ai-seo-client'); ?>
        </p>
        <?php
    }

    public function currentUserCan(string $capability): bool
    {
        return current_user_can($capability);
    }
}

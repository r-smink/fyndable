<?php

namespace SSEOAISaaS;

/**
 * Agency Role Manager
 *
 * Registers the agency_partner WordPress role with scoped capabilities
 * and provides helper methods to identify agency users and their tenant context.
 */
class AgencyRoleManager
{
    private const ROLE_NAME = 'agency_partner';

    private TenantRepository $tenants;

    public function __construct(TenantRepository $tenants)
    {
        $this->tenants = $tenants;
    }

    public function register(): void
    {
        add_action('init', [$this, 'ensureRole']);
        add_action('admin_init', [$this, 'restrictAdminAccess']);
    }

    /**
     * Ensure the agency_partner role exists with the correct capabilities.
     */
    public function ensureRole(): void
    {
        $role = get_role(self::ROLE_NAME);
        if ($role) {
            return;
        }

        add_role(
            self::ROLE_NAME,
            __('Agency Partner', 'sseo-ai-saas'),
            [
                'read' => true,
                'agency_view_dashboard' => true,
                'agency_generate_licenses' => true,
                'agency_view_tenants' => true,
                'agency_view_support' => true,
            ]
        );
    }

    /**
     * Restrict admin access: agency users can only access agency portal pages.
     */
    public function restrictAdminAccess(): void
    {
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }

        if (!$this->isAgencyUser()) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        $allowedPages = [
            'toplevel_page_sseo-ai-agency',
            'toplevel_page_sseo-ai-shell',
            'agency-portal_page_sseo-ai-agency-dashboard',
            'agency-portal_page_sseo-ai-agency-generate',
            'agency-portal_page_sseo-ai-agency-licenses',
            'agency-portal_page_sseo-ai-agency-tenants',
            'agency-portal_page_sseo-ai-agency-support',
            'agency-portal_page_sseo-ai-agency-tenant-detail',
            'agency-portal_page_sseo-ai-agency-account',
            'agency-portal_page_sseo-ai-agency-invoices',
            'agency-portal_page_sseo-ai-agency-add-licenses',
        ];

        $allowedPatterns = ['sseo-ai-agency', 'sseo-ai-shell'];

        foreach ($allowedPatterns as $pattern) {
            if (strpos($screen->id, $pattern) !== false) {
                return;
            }
        }

        if (in_array($screen->id, $allowedPages, true)) {
            return;
        }

        wp_redirect(admin_url('admin.php?page=sseo-ai-shell'));
        exit;
    }

    /**
     * Check if the current (or given) user is an agency partner.
     */
    public function isAgencyUser(?\WP_User $user = null): bool
    {
        if ($user === null) {
            $user = wp_get_current_user();
        }

        if (!$user || !$user->exists()) {
            return false;
        }

        return in_array(self::ROLE_NAME, (array)$user->roles, true);
    }

    /**
     * Get the agency tenant ID for the current (or given) user.
     */
    public function getAgencyTenantId(?int $userId = null): ?int
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return null;
        }

        $account = $this->tenants->getAgencyAccount($userId);
        if (!$account) {
            return null;
        }

        return (int)$account['tenant_id'];
    }

    /**
     * Get the full agency account record for the current (or given) user.
     */
    public function getAgencyAccount(?int $userId = null): ?array
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return null;
        }

        return $this->tenants->getAgencyAccount($userId);
    }

    /**
     * Get the agency tenant record for the current user.
     */
    public function getAgencyTenant(): ?array
    {
        $tenantId = $this->getAgencyTenantId();
        if (!$tenantId) {
            return null;
        }

        return $this->tenants->getTenantById($tenantId);
    }

    /**
     * Create a WP user with the agency_partner role and link it to an agency tenant.
     */
    public function createAgencyUser(string $email, string $companyName, int $tenantId, int $maxSubLicenses = 10): array|\WP_Error
    {
        if (empty($email) || !is_email($email)) {
            return new \WP_Error('invalid_email', __('A valid email is required', 'sseo-ai-saas'));
        }

        $existingUser = get_user_by('email', $email);
        if ($existingUser) {
            $existingUser->add_role(self::ROLE_NAME);
            $userId = $existingUser->ID;
        } else {
            $password = wp_generate_password(16, true);
            $userId = wp_create_user($email, $password, $email);
            if (is_wp_error($userId)) {
                return $userId;
            }

            $user = get_user_by('ID', $userId);
            $user->display_name = $companyName;
            wp_update_user($user);
            $user->set_role(self::ROLE_NAME);
        }

        $accountResult = $this->tenants->createAgencyAccount([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'max_sub_licenses' => $maxSubLicenses,
        ]);

        if (is_wp_error($accountResult)) {
            return $accountResult;
        }

        return [
            'user_id' => $userId,
            'account_id' => $accountResult['id'],
            'success' => true,
        ];
    }
}

<?php

namespace SSEOAISaaS;

/**
 * Customer Role Manager
 *
 * Registers the fyndable_customer WordPress role with scoped capabilities
 * for the front-end customer portal. Provides helper methods to identify
 * customer users and their tenant context, and to create customer accounts
 * automatically upon successful payment.
 */
class CustomerRoleManager
{
    private const ROLE_NAME = 'fyndable_customer';

    private TenantRepository $tenants;

    public function __construct(TenantRepository $tenants)
    {
        $this->tenants = $tenants;
    }

    public function register(): void
    {
        add_action('init', [$this, 'ensureRole']);
        add_action('admin_init', [$this, 'restrictAdminAccess']);
        add_action('sseo_ai_payment_success', [$this, 'ensureCustomerAccount'], 10, 3);
    }

    /**
     * Ensure the fyndable_customer role exists with the correct capabilities.
     */
    public function ensureRole(): void
    {
        $role = get_role(self::ROLE_NAME);
        if (!$role) {
            add_role(
                self::ROLE_NAME,
                __('Fyndable Customer', 'sseo-ai-saas'),
                [
                    'read' => true,
                    'fyndable_view_portal' => true,
                    'fyndable_manage_subscription' => true,
                    'fyndable_download_plugin' => true,
                    'fyndable_view_invoices' => true,
                ]
            );
        }

        // Register tier-specific roles for backend filtering
        $tierRoles = [
            'fyndable_starter'      => __('Fyndable Starter', 'sseo-ai-saas'),
            'fyndable_professional' => __('Fyndable Professional', 'sseo-ai-saas'),
            'fyndable_business'     => __('Fyndable Business', 'sseo-ai-saas'),
        ];

        foreach ($tierRoles as $roleName => $displayName) {
            $tierRole = get_role($roleName);
            if (!$tierRole) {
                add_role($roleName, $displayName, [
                    'read' => true,
                    'fyndable_view_portal' => true,
                    'fyndable_manage_subscription' => true,
                    'fyndable_download_plugin' => true,
                    'fyndable_view_invoices' => true,
                ]);
            }
        }
    }

    /**
     * Restrict admin access: customer users cannot access wp-admin.
     */
    public function restrictAdminAccess(): void
    {
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }

        if (!$this->isCustomerUser()) {
            return;
        }

        // Allow REST API requests
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        $portalUrl = $this->getPortalUrl();
        wp_redirect($portalUrl);
        exit;
    }

    /**
     * Check if the current (or given) user is a fyndable customer.
     */
    public function isCustomerUser(?\WP_User $user = null): bool
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
     * Get the tenant ID for the current (or given) user.
     */
    public function getCustomerTenantId(?int $userId = null): ?int
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return null;
        }

        $tenantId = get_user_meta($userId, 'fyndable_tenant_id', true);
        if ($tenantId) {
            return (int)$tenantId;
        }

        // Fallback: look up tenant by email
        $user = get_user_by('ID', $userId);
        if ($user) {
            $tenant = $this->tenants->findTenantByEmail($user->user_email);
            if ($tenant) {
                return (int)$tenant['id'];
            }
        }

        return null;
    }

    /**
     * Get the tenant record for the current user.
     */
    public function getCustomerTenant(): ?array
    {
        $tenantId = $this->getCustomerTenantId();
        if (!$tenantId) {
            return null;
        }

        return $this->tenants->getTenantById($tenantId);
    }

    /**
     * Get the tenant key for the current user.
     */
    public function getCustomerTenantKey(): ?string
    {
        $tenant = $this->getCustomerTenant();
        if (!$tenant) {
            return null;
        }

        return $tenant['tenant_key'] ?? null;
    }

    /**
     * Ensure a customer account exists for a tenant after successful payment.
     * Hooked on sseo_ai_payment_success.
     */
    public function ensureCustomerAccount(string $tenantKey, string $amount, array $meta = []): void
    {
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            return;
        }

        // Skip agency tier — agency accounts are created manually
        if (($tenant['tier'] ?? '') === 'agency') {
            return;
        }

        // Skip free tier — no account needed
        if (($tenant['tier'] ?? '') === 'free') {
            return;
        }

        $email = $tenant['email'] ?? '';
        if (empty($email) || !is_email($email)) {
            return;
        }

        $userId = $this->createCustomerUser($email, $tenant['name'] ?? '', (int)$tenant['id'], $tenant['tier'] ?? 'starter');
        if (is_wp_error($userId)) {
            error_log('SSEO AI SaaS: Failed to create customer account: ' . $userId->get_error_message());
        }
    }

    /**
     * Create a WP user with the fyndable_customer role and link it to a tenant.
     * If the user already exists, upgrade their role.
     *
     * @return int|\WP_Error User ID on success, WP_Error on failure.
     */
    public function createCustomerUser(string $email, string $name, int $tenantId, string $tier = 'starter'): int|\WP_Error
    {
        if (empty($email) || !is_email($email)) {
            return new \WP_Error('invalid_email', __('A valid email is required', 'sseo-ai-saas'));
        }

        $existingUser = get_user_by('email', $email);
        if ($existingUser) {
            // Upgrade existing user to customer role if they don't have it
            if (!in_array(self::ROLE_NAME, (array)$existingUser->roles, true)) {
                // Don't downgrade admins or agency partners
                if (!in_array('administrator', (array)$existingUser->roles, true)
                    && !in_array('agency_partner', (array)$existingUser->roles, true)) {
                    $existingUser->set_role(self::ROLE_NAME);
                }
            }
            $userId = $existingUser->ID;
        } else {
            $password = wp_generate_password(20, true);
            $userId = wp_create_user($email, $password, $email);
            if (is_wp_error($userId)) {
                return $userId;
            }

            $user = get_user_by('ID', $userId);
            if ($name) {
                $user->display_name = $name;
                wp_update_user($user);
            }
            $user->set_role(self::ROLE_NAME);

            // Also assign tier-specific role for backend filtering
            $tierRole = $this->getTierRoleName($tier);
            if ($tierRole) {
                $user->add_role($tierRole);
            }

            // Send password reset email so the customer can set their own password
            $this->sendWelcomeEmail($email, $name, $userId);
        }

        // Store tenant linkage in user meta
        update_user_meta($userId, 'fyndable_tenant_id', $tenantId);
        update_user_meta($userId, 'fyndable_tier', $tier);
        update_user_meta($userId, 'fyndable_customer_since', current_time('mysql'));

        // Ensure tier-specific role is assigned (handles existing users too)
        $tierRole = $this->getTierRoleName($tier);
        if ($tierRole) {
            $user = get_user_by('ID', $userId);
            if ($user && !in_array($tierRole, (array)$user->roles, true)) {
                $user->add_role($tierRole);
            }
        }

        return (int)$userId;
    }

    /**
     * Get the tier-specific role name for a given tier.
     */
    private function getTierRoleName(string $tier): ?string
    {
        $map = [
            'starter'      => 'fyndable_starter',
            'professional' => 'fyndable_professional',
            'business'     => 'fyndable_business',
        ];
        return $map[$tier] ?? null;
    }

    /**
     * Send a welcome email with password reset link to the new customer.
     */
    private function sendWelcomeEmail(string $email, string $name, int $userId): void
    {
        $portalUrl = $this->getPortalUrl();

        // Generate a password reset key
        $key = get_password_reset_key(get_user_by('ID', $userId));
        if (is_wp_error($key)) {
            return;
        }

        $resetUrl = network_site_url("wp-login.php?action=rp&key={$key}&login=" . rawurlencode($email));

        $subject = sprintf(__('Welcome to Fyndable SmartSEO — Your account is ready', 'sseo-ai-saas'));
        $message = sprintf(
            __("Hi %s,

Your Fyndable SmartSEO account has been created! You can now manage your subscription, view invoices, download the plugin, and track your usage.

Set your password and log in to your customer portal:
%s

After logging in, you can download the Fyndable plugin and find your license key in the License tab.

If you have any questions, feel free to reply to this email.

— The Fyndable Team", 'sseo-ai-saas'),
            $name ?: 'there',
            $resetUrl
        );

        wp_mail($email, $subject, $message);
    }

    /**
     * Get the URL of the customer portal page.
     */
    public function getPortalUrl(): string
    {
        $portalPageId = (int) get_option('sseo_ai_saas_customer_portal_page', 0);
        if ($portalPageId > 0) {
            $url = get_permalink($portalPageId);
            if ($url) {
                return $url;
            }
        }
        return home_url('/customer-portal/');
    }
}

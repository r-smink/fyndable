<?php

namespace SSEOAISaaS;

/**
 * Support Admin Interface
 *
 * Adds a "Support Tickets" page to the SaaS dashboard where staff can view,
 * filter, reply to and manage tickets submitted by client sites.
 */
class SupportAdmin
{
    private TenantRepository $tenants;
    private SupportTickets $supportTickets;

    public function __construct(TenantRepository $tenants, SupportTickets $supportTickets)
    {
        $this->tenants = $tenants;
        $this->supportTickets = $supportTickets;
    }

    public function register(): void
    {
        add_submenu_page(
            'sseo-ai-licenses',
            __('Support Tickets', 'sseo-ai-saas'),
            __('Support Tickets', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-support-tickets',
            [$this, 'renderTicketsPage']
        );
    }

    public function renderTicketsPage(): void
    {
        $this->processActions();

        $ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;

        if ($ticketId > 0) {
            $this->renderTicketDetail($ticketId);
            return;
        }

        $this->renderTicketList();
    }

    private function processActions(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'sseo-ai-saas'));
        }

        if (isset($_POST['sseo_ai_add_reply']) && isset($_POST['sseo_ai_reply_nonce'])) {
            if (!wp_verify_nonce($_POST['sseo_ai_reply_nonce'], 'sseo_ai_add_reply')) {
                wp_die(__('Security check failed.', 'sseo-ai-saas'));
            }

            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $message = sanitize_textarea_field($_POST['reply_message'] ?? '');

            if ($ticketId > 0 && !empty($message)) {
                $currentUser = wp_get_current_user();
                $authorName = $currentUser->exists() ? $currentUser->display_name : __('Support', 'sseo-ai-saas');

                $screenshots = [];
                if (!empty($_FILES['reply_screenshots']['tmp_name'][0])) {
                    $screenshots = $this->handleUploads($_FILES['reply_screenshots']);
                }

                $this->supportTickets->addStaffReply($ticketId, $authorName, $message, $screenshots);

                wp_redirect(admin_url('admin.php?page=sseo-ai-support-tickets&ticket_id=' . $ticketId . '&reply_sent=1'));
                exit;
            }
        }

        if (isset($_POST['sseo_ai_update_ticket']) && isset($_POST['sseo_ai_update_nonce'])) {
            if (!wp_verify_nonce($_POST['sseo_ai_update_nonce'], 'sseo_ai_update_ticket')) {
                wp_die(__('Security check failed.', 'sseo-ai-saas'));
            }

            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $status = sanitize_text_field($_POST['ticket_status'] ?? '');
            $priority = sanitize_text_field($_POST['ticket_priority'] ?? '');

            if ($ticketId > 0) {
                $this->supportTickets->updateTicket($ticketId, [
                    'status' => $status,
                    'priority' => $priority,
                ]);

                wp_redirect(admin_url('admin.php?page=sseo-ai-support-tickets&ticket_id=' . $ticketId . '&updated=1'));
                exit;
            }
        }
    }

    private function renderTicketList(): void
    {
        $filters = [
            'status' => sanitize_text_field($_GET['status'] ?? ''),
            'priority' => sanitize_text_field($_GET['priority'] ?? ''),
            'search' => sanitize_text_field($_GET['search'] ?? ''),
        ];

        $tickets = $this->supportTickets->getAllTickets(array_filter($filters));
        $statuses = ['open' => __('Open', 'sseo-ai-saas'), 'reaction' => __('Reaction', 'sseo-ai-saas'), 'closed' => __('Closed', 'sseo-ai-saas')];
        $priorities = ['low' => __('Low', 'sseo-ai-saas'), 'middle' => __('Middle', 'sseo-ai-saas'), 'high' => __('High', 'sseo-ai-saas')];

        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Support Tickets', 'sseo-ai-saas'); ?></h1>

            <div class="sseo-ai-card">
                <form method="get" style="margin-bottom: 0;">
                    <input type="hidden" name="page" value="sseo-ai-support-tickets">
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                        <div>
                            <label for="status-filter"><?php esc_html_e('Status', 'sseo-ai-saas'); ?></label><br>
                            <select name="status" id="status-filter">
                                <option value=""><?php esc_html_e('All statuses', 'sseo-ai-saas'); ?></option>
                                <?php foreach ($statuses as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($filters['status'], $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="priority-filter"><?php esc_html_e('Priority', 'sseo-ai-saas'); ?></label><br>
                            <select name="priority" id="priority-filter">
                                <option value=""><?php esc_html_e('All priorities', 'sseo-ai-saas'); ?></option>
                                <?php foreach ($priorities as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($filters['priority'], $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex: 1; min-width: 200px;">
                            <label for="search-filter"><?php esc_html_e('Search', 'sseo-ai-saas'); ?></label><br>
                            <input type="text" name="search" id="search-filter" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php esc_attr_e('Subject, message, tenant...', 'sseo-ai-saas'); ?>" style="width: 100%;">
                        </div>
                        <div>
                            <button type="submit" class="button button-primary"><?php esc_html_e('Filter', 'sseo-ai-saas'); ?></button>
                            <a href="<?php echo admin_url('admin.php?page=sseo-ai-support-tickets'); ?>" class="button"><?php esc_html_e('Reset', 'sseo-ai-saas'); ?></a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="sseo-ai-card">
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Tenant', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Subject', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Priority', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Last update', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;"><?php esc_html_e('No tickets found.', 'sseo-ai-saas'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td>#<?php echo (int)$ticket['id']; ?></td>
                                    <td>
                                        <?php echo esc_html($ticket['tenant_name']); ?><br>
                                        <small><?php echo esc_html($ticket['tenant_email']); ?></small>
                                    </td>
                                    <td><?php echo esc_html($ticket['subject']); ?></td>
                                    <td><?php echo esc_html($priorities[$ticket['priority']] ?? ucfirst($ticket['priority'])); ?></td>
                                    <td><?php echo esc_html($statuses[$ticket['status']] ?? ucfirst($ticket['status'])); ?></td>
                                    <td><?php echo esc_html(human_time_diff(strtotime($ticket['updated_at']), current_time('timestamp')) . ' ' . __('ago', 'sseo-ai-saas')); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=sseo-ai-support-tickets&ticket_id=' . (int)$ticket['id']); ?>" class="button button-small">
                                            <?php esc_html_e('View', 'sseo-ai-saas'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private function renderTicketDetail(int $ticketId): void
    {
        $ticket = $this->supportTickets->getTicketById($ticketId);
        if (!$ticket) {
            echo '<div class="wrap sseo-ai-license-admin"><div class="notice notice-error"><p>' . esc_html__('Ticket not found.', 'sseo-ai-saas') . '</p></div></div>';
            return;
        }

        $statuses = ['open' => __('Open', 'sseo-ai-saas'), 'reaction' => __('Reaction', 'sseo-ai-saas'), 'closed' => __('Closed', 'sseo-ai-saas')];
        $priorities = ['low' => __('Low', 'sseo-ai-saas'), 'middle' => __('Middle', 'sseo-ai-saas'), 'high' => __('High', 'sseo-ai-saas')];

        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1>
                <?php echo esc_html(sprintf(__('Ticket #%d: %s', 'sseo-ai-saas'), $ticket['id'], $ticket['subject'])); ?>
            </h1>

            <?php if (isset($_GET['reply_sent'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Reply sent.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['updated'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Ticket updated.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>

            <div class="sseo-ai-grid-2">
                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Ticket details', 'sseo-ai-saas'); ?></h2>
                    <p><strong><?php esc_html_e('Tenant:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html($ticket['tenant_name']); ?> (<?php echo esc_html($ticket['tenant_email']); ?>)</p>
                    <p><strong><?php esc_html_e('License:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html($ticket['license_key']); ?></p>
                    <p><strong><?php esc_html_e('Created:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html($ticket['created_at']); ?></p>

                    <form method="post">
                        <?php wp_nonce_field('sseo_ai_update_ticket', 'sseo_ai_update_nonce'); ?>
                        <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['id']; ?>">
                        <table class="form-table">
                            <tr>
                                <th><label for="ticket_status"><?php esc_html_e('Status', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <select name="ticket_status" id="ticket_status">
                                        <?php foreach ($statuses as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($ticket['status'], $value); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="ticket_priority"><?php esc_html_e('Priority', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <select name="ticket_priority" id="ticket_priority">
                                        <?php foreach ($priorities as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($ticket['priority'], $value); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(__('Update ticket', 'sseo-ai-saas'), 'primary', 'sseo_ai_update_ticket'); ?>
                    </form>
                </div>

                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Original message', 'sseo-ai-saas'); ?></h2>
                    <div style="background: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <p><?php echo nl2br(esc_html($ticket['message'])); ?></p>
                        <?php if (!empty($ticket['screenshots'])): ?>
                            <p><strong><?php esc_html_e('Screenshots:', 'sseo-ai-saas'); ?></strong></p>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <?php foreach ($ticket['screenshots'] as $url): ?>
                                    <a href="<?php echo esc_url($url); ?>" target="_blank" style="display: inline-block; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden;">
                                        <img src="<?php echo esc_url($url); ?>" style="max-width: 150px; max-height: 150px; display: block;">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Conversation', 'sseo-ai-saas'); ?></h2>
                <?php if (empty($ticket['replies'])): ?>
                    <p><?php esc_html_e('No replies yet.', 'sseo-ai-saas'); ?></p>
                <?php else: ?>
                    <?php foreach ($ticket['replies'] as $reply): ?>
                        <div style="margin-bottom: 20px; padding: 15px; border-radius: 8px; background: <?php echo $reply['is_staff'] ? '#f0f6fc' : '#fff'; ?>; border: 1px solid #e5e7eb;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-weight: 600;">
                                <span><?php echo $reply['is_staff'] ? esc_html($reply['author_name'] ?: __('Support', 'sseo-ai-saas')) : esc_html($ticket['tenant_name']); ?></span>
                                <small style="color: #6b7280;"><?php echo esc_html($reply['created_at']); ?></small>
                            </div>
                            <p><?php echo nl2br(esc_html($reply['message'])); ?></p>
                            <?php if (!empty($reply['screenshots'])): ?>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <?php foreach ($reply['screenshots'] as $url): ?>
                                        <a href="<?php echo esc_url($url); ?>" target="_blank" style="display: inline-block; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden;">
                                            <img src="<?php echo esc_url($url); ?>" style="max-width: 120px; max-height: 120px; display: block;">
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <h3><?php esc_html_e('Add reply', 'sseo-ai-saas'); ?></h3>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('sseo_ai_add_reply', 'sseo_ai_reply_nonce'); ?>
                    <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['id']; ?>">
                    <p>
                        <label for="reply_message"><strong><?php esc_html_e('Message', 'sseo-ai-saas'); ?></strong></label><br>
                        <textarea name="reply_message" id="reply_message" rows="6" style="width: 100%;" required></textarea>
                    </p>
                    <p>
                        <label for="reply_screenshots"><strong><?php esc_html_e('Screenshots', 'sseo-ai-saas'); ?></strong></label><br>
                        <input type="file" name="reply_screenshots[]" id="reply_screenshots" multiple accept="image/*">
                    </p>
                    <?php submit_button(__('Send reply', 'sseo-ai-saas'), 'primary', 'sseo_ai_add_reply'); ?>
                </form>
            </div>

            <p>
                <a href="<?php echo admin_url('admin.php?page=sseo-ai-support-tickets'); ?>" class="button">&larr; <?php esc_html_e('Back to tickets', 'sseo-ai-saas'); ?></a>
            </p>
        </div>
        <?php
    }

    private function handleUploads(array $files): array
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $urls = [];
        $count = count($files['tmp_name']);

        for ($i = 0; $i < $count; $i++) {
            if (empty($files['tmp_name'][$i])) {
                continue;
            }

            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];

            $upload = wp_handle_upload($file, ['test_form' => false]);
            if (!empty($upload['url'])) {
                $urls[] = $upload['url'];
            }
        }

        return $urls;
    }
}

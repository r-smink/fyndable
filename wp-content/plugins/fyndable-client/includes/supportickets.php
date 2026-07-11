<?php

namespace SSEOAIClient;

/**
 * Support Tickets — client-side UI
 *
 * Renders the “Support” page in the Fyndable dashboard shell. Lets customers
 * view, create and reply to support tickets that are stored centrally on the
 * SaaS dashboard.
 */
class Supportickets
{
    private Settings $settings;
    private DashboardAPI $dashboardAPI;

    private string $pageSlug = 'ai-seo-support';
    private array $priorityLabels;
    private array $statusLabels;

    public function __construct(Settings $settings, DashboardAPI $dashboardAPI)
    {
        $this->settings = $settings;
        $this->dashboardAPI = $dashboardAPI;

        $this->priorityLabels = [
            'low' => __('Low', 'ai-seo-client'),
            'middle' => __('Middle', 'ai-seo-client'),
            'high' => __('High', 'ai-seo-client'),
        ];

        $this->statusLabels = [
            'open' => __('Open', 'ai-seo-client'),
            'reaction' => __('Reaction', 'ai-seo-client'),
            'closed' => __('Closed', 'ai-seo-client'),
        ];
    }

    /**
     * Render the support page.
     */
    public function renderPage(): void
    {
        $this->processPostActions();

        $viewTicketId = isset($_GET['view']) ? (int)$_GET['view'] : 0;

        ?>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Support', 'ai-seo-client'); ?></h1>
            </div>
            <div class="sseo-ai-content">
                <?php
                if ($viewTicketId > 0) {
                    $this->renderTicketDetail($viewTicketId);
                } else {
                    $this->renderTicketList();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Process form submissions.
     */
    private function processPostActions(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (isset($_POST['create_support_ticket']) && isset($_POST['support_ticket_nonce'])) {
            if (!wp_verify_nonce($_POST['support_ticket_nonce'], 'create_support_ticket')) {
                wp_die(__('Security check failed.', 'ai-seo-client'));
            }

            if (!current_user_can('manage_options')) {
                wp_die(__('Insufficient permissions.', 'ai-seo-client'));
            }

            $subject = sanitize_text_field($_POST['ticket_subject'] ?? '');
            $message = sanitize_textarea_field($_POST['ticket_message'] ?? '');
            $priority = sanitize_text_field($_POST['ticket_priority'] ?? 'middle');

            if (empty($subject) || empty($message)) {
                return;
            }

            if (!in_array($priority, ['low', 'middle', 'high'], true)) {
                $priority = 'middle';
            }

            $screenshots = [];
            if (!empty($_FILES['ticket_screenshots']['tmp_name'][0])) {
                $screenshots = $this->uploadScreenshots($_FILES['ticket_screenshots']);
            }

            $result = $this->dashboardAPI->createSupportTicket($subject, $message, $priority, $screenshots);

            if (is_wp_error($result)) {
                $this->redirectWithMessage('error=' . urlencode($result->get_error_message()));
                return;
            }

            $ticketId = $result['ticket_id'] ?? 0;
            if ($ticketId > 0) {
                $this->redirectWithMessage('view=' . $ticketId . '&created=1');
            }

            return;
        }

        if (isset($_POST['add_support_reply']) && isset($_POST['support_reply_nonce'])) {
            if (!wp_verify_nonce($_POST['support_reply_nonce'], 'add_support_reply')) {
                wp_die(__('Security check failed.', 'ai-seo-client'));
            }

            if (!current_user_can('manage_options')) {
                wp_die(__('Insufficient permissions.', 'ai-seo-client'));
            }

            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $message = sanitize_textarea_field($_POST['reply_message'] ?? '');

            if ($ticketId <= 0 || empty($message)) {
                return;
            }

            $screenshots = [];
            if (!empty($_FILES['reply_screenshots']['tmp_name'][0])) {
                $screenshots = $this->uploadScreenshots($_FILES['reply_screenshots']);
            }

            $result = $this->dashboardAPI->addSupportReply($ticketId, $message, $screenshots);

            if (is_wp_error($result)) {
                $this->redirectWithMessage('view=' . $ticketId . '&error=' . urlencode($result->get_error_message()));
                return;
            }

            $this->redirectWithMessage('view=' . $ticketId . '&reply_sent=1');
        }
    }

    /**
     * Redirect back to the support page preserving the iframe parameter.
     */
    private function redirectWithMessage(string $query): void
    {
        $url = admin_url('admin.php?page=' . $this->pageSlug . '&' . $query);
        if (isset($_GET['fyndable_shell'])) {
            $url = add_query_arg('fyndable_shell', '1', $url);
        }
        wp_redirect($url);
        exit;
    }

    /**
     * Upload screenshots via the Dashboard API.
     */
    private function uploadScreenshots(array $files): array
    {
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

            $result = $this->dashboardAPI->uploadSupportScreenshot($file);
            if (!is_wp_error($result) && !empty($result['url'])) {
                $urls[] = $result['url'];
            }
        }

        return $urls;
    }

    /**
     * Render the ticket list and create form.
     */
    private function renderTicketList(): void
    {
        $tickets = $this->dashboardAPI->getSupportTickets();
        $hasError = is_wp_error($tickets);
        $errorMessage = $hasError ? $tickets->get_error_message() : '';
        $ticketList = (!$hasError && !empty($tickets['tickets'])) ? $tickets['tickets'] : [];
        ?>
        <style>
            .sseo-support-grid { display: grid; grid-template-columns: 1fr 380px; gap: 30px; }
            .sseo-support-card { background: rgba(255,255,255,0.95); border-radius: 12px; padding: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); }
            .sseo-support-card h2 { margin-top: 0; font-size: 20px; }
            .sseo-form-field { margin-bottom: 18px; }
            .sseo-form-field label { display: block; font-weight: 600; margin-bottom: 6px; color: #374151; }
            .sseo-form-field input[type="text"],
            .sseo-form-field textarea,
            .sseo-form-field select { width: 100%; padding: 10px 14px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
            .sseo-form-field input:focus,
            .sseo-form-field textarea:focus,
            .sseo-form-field select:focus { border-color: #FF4D00; outline: none; box-shadow: 0 0 0 3px rgba(255,77,0,.1); }
            .sseo-ticket-list { list-style: none; margin: 0; padding: 0; }
            .sseo-ticket-item { border-bottom: 1px solid #e5e7eb; padding: 18px 0; }
            .sseo-ticket-item:last-child { border-bottom: none; }
            .sseo-ticket-item a { text-decoration: none; color: #111827; }
            .sseo-ticket-item a:hover { color: #FF4D00; }
            .sseo-ticket-meta { display: flex; gap: 10px; margin-top: 8px; flex-wrap: wrap; }
            .sseo-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
            .sseo-badge.status-open { background: #dbeafe; color: #1e40af; }
            .sseo-badge.status-reaction { background: #fef3c7; color: #92400e; }
            .sseo-badge.status-closed { background: #d1fae5; color: #065f46; }
            .sseo-badge.priority-high { background: #fee2e2; color: #991b1b; }
            .sseo-badge.priority-middle { background: #fef3c7; color: #92400e; }
            .sseo-badge.priority-low { background: #f3f4f6; color: #374151; }
            @media (max-width: 900px) { .sseo-support-grid { grid-template-columns: 1fr; } }
        </style>

        <?php if (isset($_GET['created'])): ?>
            <div class="sseo-support-card" style="margin-bottom: 30px; background: #d1fae5; color: #065f46;">
                <strong><?php esc_html_e('Ticket created successfully.', 'ai-seo-client'); ?></strong>
            </div>
        <?php endif; ?>

        <?php if ($hasError): ?>
            <div class="sseo-support-card" style="margin-bottom: 30px; background: #fee2e2; color: #991b1b;">
                <strong><?php echo esc_html($errorMessage); ?></strong>
            </div>
        <?php endif; ?>

        <div class="sseo-support-grid">
            <div class="sseo-support-card">
                <h2><?php esc_html_e('Your tickets', 'ai-seo-client'); ?></h2>
                <?php if (empty($ticketList) && !$hasError): ?>
                    <p><?php esc_html_e('You have no support tickets yet.', 'ai-seo-client'); ?></p>
                <?php elseif (!empty($ticketList)): ?>
                    <ul class="sseo-ticket-list">
                        <?php foreach ($ticketList as $ticket): ?>
                            <li class="sseo-ticket-item">
                                <a href="<?php echo esc_url($this->pageUrl(['view' => $ticket['id']])); ?>">
                                    <strong><?php echo esc_html($ticket['subject']); ?></strong>
                                </a>
                                <div class="sseo-ticket-meta">
                                    <span class="sseo-badge status-<?php echo esc_attr($ticket['status']); ?>">
                                        <?php echo esc_html($this->statusLabels[$ticket['status']] ?? ucfirst($ticket['status'])); ?>
                                    </span>
                                    <span class="sseo-badge priority-<?php echo esc_attr($ticket['priority']); ?>">
                                        <?php echo esc_html($this->priorityLabels[$ticket['priority']] ?? ucfirst($ticket['priority'])); ?>
                                    </span>
                                    <span style="color: #6b7280; font-size: 13px;">
                                        <?php echo esc_html(human_time_diff(strtotime($ticket['updated_at']), current_time('timestamp')) . ' ' . __('ago', 'ai-seo-client')); ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="sseo-support-card">
                <h2><?php esc_html_e('Create ticket', 'ai-seo-client'); ?></h2>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('create_support_ticket', 'support_ticket_nonce'); ?>
                    <div class="sseo-form-field">
                        <label for="ticket_subject"><?php esc_html_e('Subject', 'ai-seo-client'); ?></label>
                        <input type="text" name="ticket_subject" id="ticket_subject" required>
                    </div>
                    <div class="sseo-form-field">
                        <label for="ticket_priority"><?php esc_html_e('Priority', 'ai-seo-client'); ?></label>
                        <select name="ticket_priority" id="ticket_priority">
                            <option value="low"><?php esc_html_e('Low', 'ai-seo-client'); ?></option>
                            <option value="middle" selected><?php esc_html_e('Middle', 'ai-seo-client'); ?></option>
                            <option value="high"><?php esc_html_e('High', 'ai-seo-client'); ?></option>
                        </select>
                    </div>
                    <div class="sseo-form-field">
                        <label for="ticket_message"><?php esc_html_e('Message', 'ai-seo-client'); ?></label>
                        <textarea name="ticket_message" id="ticket_message" rows="6" required></textarea>
                    </div>
                    <div class="sseo-form-field">
                        <label for="ticket_screenshots"><?php esc_html_e('Screenshots', 'ai-seo-client'); ?></label>
                        <input type="file" name="ticket_screenshots[]" id="ticket_screenshots" multiple accept="image/*">
                    </div>
                    <?php submit_button(__('Submit ticket', 'ai-seo-client'), 'primary', 'create_support_ticket'); ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render a single ticket with replies and a reply form.
     */
    private function renderTicketDetail(int $ticketId): void
    {
        $result = $this->dashboardAPI->getSupportTicket($ticketId);
        if (is_wp_error($result) || empty($result['ticket'])) {
            ?>
            <div class="sseo-support-card" style="background: #fee2e2; color: #991b1b;">
                <?php esc_html_e('Could not load ticket.', 'ai-seo-client'); ?>
                <?php if (is_wp_error($result)): ?>
                    <p><?php echo esc_html($result->get_error_message()); ?></p>
                <?php endif; ?>
                <p><a href="<?php echo esc_url($this->pageUrl()); ?>" class="button"><?php esc_html_e('Back', 'ai-seo-client'); ?></a></p>
            </div>
            <?php
            return;
        }

        $ticket = $result['ticket'];
        ?>
        <style>
            .sseo-ticket-detail { background: rgba(255,255,255,0.95); border-radius: 12px; padding: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); margin-bottom: 30px; }
            .sseo-reply-list { margin: 20px 0; }
            .sseo-reply { padding: 18px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e5e7eb; }
            .sseo-reply.staff { background: #f0f6fc; }
            .sseo-reply.customer { background: #fff; }
            .sseo-reply-header { display: flex; justify-content: space-between; margin-bottom: 8px; font-weight: 600; }
            .sseo-screenshots { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
            .sseo-screenshots a { display: inline-block; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; }
            .sseo-screenshots img { max-width: 150px; max-height: 150px; display: block; }
        </style>

        <?php if (isset($_GET['created'])): ?>
            <div class="sseo-ticket-detail" style="background: #d1fae5; color: #065f46; margin-bottom: 20px;">
                <strong><?php esc_html_e('Ticket created successfully.', 'ai-seo-client'); ?></strong>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['reply_sent'])): ?>
            <div class="sseo-ticket-detail" style="background: #d1fae5; color: #065f46; margin-bottom: 20px;">
                <strong><?php esc_html_e('Reply sent.', 'ai-seo-client'); ?></strong>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="sseo-ticket-detail" style="background: #fee2e2; color: #991b1b; margin-bottom: 20px;">
                <strong><?php echo esc_html(urldecode($_GET['error'])); ?></strong>
            </div>
        <?php endif; ?>

        <div class="sseo-ticket-detail">
            <h2><?php echo esc_html($ticket['subject']); ?></h2>
            <div class="sseo-ticket-meta">
                <span class="sseo-badge status-<?php echo esc_attr($ticket['status']); ?>">
                    <?php echo esc_html($this->statusLabels[$ticket['status']] ?? ucfirst($ticket['status'])); ?>
                </span>
                <span class="sseo-badge priority-<?php echo esc_attr($ticket['priority']); ?>">
                    <?php echo esc_html($this->priorityLabels[$ticket['priority']] ?? ucfirst($ticket['priority'])); ?>
                </span>
                <span style="color: #6b7280; font-size: 13px;">
                    <?php echo esc_html(sprintf(__('Created %s', 'ai-seo-client'), $ticket['created_at'])); ?>
                </span>
            </div>
            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
            <p><?php echo nl2br(esc_html($ticket['message'])); ?></p>
            <?php if (!empty($ticket['screenshots'])): ?>
                <div class="sseo-screenshots">
                    <?php foreach ($ticket['screenshots'] as $url): ?>
                        <a href="<?php echo esc_url($url); ?>" target="_blank">
                            <img src="<?php echo esc_url($url); ?>" alt="<?php esc_attr_e('Screenshot', 'ai-seo-client'); ?>">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="sseo-ticket-detail">
            <h2><?php esc_html_e('Replies', 'ai-seo-client'); ?></h2>
            <?php if (empty($ticket['replies'])): ?>
                <p><?php esc_html_e('No replies yet.', 'ai-seo-client'); ?></p>
            <?php else: ?>
                <div class="sseo-reply-list">
                    <?php foreach ($ticket['replies'] as $reply): ?>
                        <div class="sseo-reply <?php echo $reply['is_staff'] ? 'staff' : 'customer'; ?>">
                            <div class="sseo-reply-header">
                                <span><?php echo $reply['is_staff'] ? esc_html($reply['author_name'] ?: __('Support', 'ai-seo-client')) : esc_html(__('You', 'ai-seo-client')); ?></span>
                                <small style="color: #6b7280;"><?php echo esc_html($reply['created_at']); ?></small>
                            </div>
                            <p><?php echo nl2br(esc_html($reply['message'])); ?></p>
                            <?php if (!empty($reply['screenshots'])): ?>
                                <div class="sseo-screenshots">
                                    <?php foreach ($reply['screenshots'] as $url): ?>
                                        <a href="<?php echo esc_url($url); ?>" target="_blank">
                                            <img src="<?php echo esc_url($url); ?>" alt="<?php esc_attr_e('Screenshot', 'ai-seo-client'); ?>">
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($ticket['status'] !== 'closed'): ?>
                <h3><?php esc_html_e('Add reply', 'ai-seo-client'); ?></h3>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('add_support_reply', 'support_reply_nonce'); ?>
                    <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['id']; ?>">
                    <div class="sseo-form-field">
                        <label for="reply_message"><?php esc_html_e('Message', 'ai-seo-client'); ?></label>
                        <textarea name="reply_message" id="reply_message" rows="5" required></textarea>
                    </div>
                    <div class="sseo-form-field">
                        <label for="reply_screenshots"><?php esc_html_e('Screenshots', 'ai-seo-client'); ?></label>
                        <input type="file" name="reply_screenshots[]" id="reply_screenshots" multiple accept="image/*">
                    </div>
                    <?php submit_button(__('Send reply', 'ai-seo-client'), 'primary', 'add_support_reply'); ?>
                </form>
            <?php else: ?>
                <p><em><?php esc_html_e('This ticket is closed. Create a new ticket if you need further help.', 'ai-seo-client'); ?></em></p>
            <?php endif; ?>

            <p style="margin-top: 20px;">
                <a href="<?php echo esc_url($this->pageUrl()); ?>" class="button">&larr; <?php esc_html_e('Back to tickets', 'ai-seo-client'); ?></a>
            </p>
        </div>
        <?php
    }

    /**
     * Build a support page URL preserving the iframe flag.
     */
    private function pageUrl(array $params = []): string
    {
        $query = array_merge(['page' => $this->pageSlug], $params);
        $url = admin_url('admin.php?' . http_build_query($query));

        if (isset($_GET['fyndable_shell'])) {
            $url = add_query_arg('fyndable_shell', '1', $url);
        }

        return $url;
    }
}

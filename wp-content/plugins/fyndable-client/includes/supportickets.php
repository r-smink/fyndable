<?php

namespace SSEOAIClient;

/**
 * Support Tickets — client-side UI
 *
 * Renders the “Support” page in the dashboard shell. Lets customers
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
            'middle' => __('Medium', 'ai-seo-client'),
            'high' => __('High', 'ai-seo-client'),
        ];

        $this->statusLabels = [
            'open' => __('Open', 'ai-seo-client'),
            'reaction' => __('In Progress', 'ai-seo-client'),
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
        <div class="wrap fyn-animate-in" style="max-width:1440px;margin:0 auto;padding:28px 32px 48px;">
            <div class="fyn-page-header">
                <div class="fyn-page-header-left">
                    <div class="fyn-breadcrumb">Fyndable &rsaquo; <span class="fyn-crumb"><?php esc_html_e('Support', 'ai-seo-client'); ?></span></div>
                    <h1><?php esc_html_e('Support', 'ai-seo-client'); ?></h1>
                    <p class="fyn-page-desc"><?php esc_html_e('View and create tickets for support.', 'ai-seo-client'); ?></p>
                </div>
            </div>
            <?php
            if ($viewTicketId > 0) {
                $this->renderTicketDetail($viewTicketId);
            } else {
                $this->renderTicketList();
            }
            ?>
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

        // Chatbot enable/disable toggle
        if (isset($_POST['toggle_chatbot']) && isset($_POST['chatbot_toggle_nonce'])) {
            if (!wp_verify_nonce($_POST['chatbot_toggle_nonce'], 'toggle_chatbot')) {
                wp_die(__('Security check failed.', 'ai-seo-client'));
            }

            if (!current_user_can('manage_options')) {
                wp_die(__('Insufficient permissions.', 'ai-seo-client'));
            }

            $enabled = !empty($_POST['chatbot_enabled']) ? 1 : 0;
            update_option('sseo_ai_chatbot_disabled', $enabled ? 0 : 1);

            $this->redirectWithMessage('chatbot_toggled=1');
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
            .sseo-support-grid { display: grid; grid-template-columns: 1fr 380px; gap: 20px; }
            .sseo-form-field { margin-bottom: 18px; }
            .sseo-form-field label { display: block; font-weight: 600; margin-bottom: 6px; color: var(--text-h); font-size: 13px; }
            .sseo-form-field input[type="text"],
            .sseo-form-field textarea,
            .sseo-form-field select { width: 100%; }
            .sseo-ticket-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; }
            .sseo-ticket-item { border: 1px solid var(--card-border); border-radius: 10px; padding: 18px 22px; transition: all .2s ease; cursor: pointer; background: var(--card); }
            .sseo-ticket-item:hover { border-color: var(--fyn-blue); box-shadow: 0 4px 12px rgba(55,159,211,.1); transform: translateY(-1px); }
            .sseo-ticket-item a { text-decoration: none; color: var(--text-h); }
            .sseo-ticket-item a:hover { color: var(--fyn-blue); }
            .sseo-ticket-meta { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; align-items: center; }
            .sseo-file-hint { font-size: 12px; color: var(--text-m); margin-top: 4px; }
            .sseo-file-drop { border: 2px dashed var(--divide); border-radius: 8px; padding: 20px; text-align: center; transition: all .2s ease; cursor: pointer; }
            .sseo-file-drop:hover { border-color: var(--fyn-blue); background: var(--well); }
            .sseo-file-drop input[type="file"] { width: 100%; }
            @media (max-width: 900px) { .sseo-support-grid { grid-template-columns: 1fr; } }
        </style>

        <?php if (isset($_GET['created'])): ?>
            <div class="fyn-card" style="margin-bottom:16px;background:var(--fyn-success-bg,#d1fae5);color:var(--fyn-success);padding:14px 20px;">
                <strong><?php esc_html_e('Ticket created successfully.', 'ai-seo-client'); ?></strong>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['chatbot_toggled'])): ?>
            <div class="fyn-card" style="margin-bottom:16px;background:var(--fyn-success-bg,#d1fae5);color:var(--fyn-success);padding:14px 20px;">
                <strong><?php esc_html_e('Chatbot setting saved.', 'ai-seo-client'); ?></strong>
            </div>
        <?php endif; ?>

        <?php if ($hasError): ?>
            <div class="fyn-card" style="margin-bottom:16px;background:var(--fyn-danger-bg,#fee2e2);color:var(--fyn-danger);padding:14px 20px;">
                <strong><?php echo esc_html($errorMessage); ?></strong>
            </div>
        <?php endif; ?>

        <?php $this->renderChatbotToggle(); ?>

        <div class="sseo-support-grid">
            <div class="fyn-card">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-tickets-alt"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Your Tickets', 'ai-seo-client'); ?></h3>
                </div>
                <?php if (empty($ticketList) && !$hasError): ?>
                    <p style="color:var(--text-m);"><?php esc_html_e('You have no support tickets yet.', 'ai-seo-client'); ?></p>
                <?php elseif (!empty($ticketList)): ?>
                    <ul class="sseo-ticket-list">
                        <?php foreach ($ticketList as $ticket): ?>
                            <li class="sseo-ticket-item">
                                <a href="<?php echo esc_url($this->pageUrl(['view' => $ticket['id']])); ?>">
                                    <strong><?php echo esc_html($ticket['subject']); ?></strong>
                                </a>
                                <div class="sseo-ticket-meta">
                                    <span class="fyn-badge fyn-badge--<?php echo $ticket['status'] === 'closed' ? 'success' : ($ticket['status'] === 'reaction' ? 'warning' : 'info'); ?>">
                                        <?php echo esc_html($this->statusLabels[$ticket['status']] ?? ucfirst($ticket['status'])); ?>
                                    </span>
                                    <span class="fyn-badge fyn-badge--<?php echo $ticket['priority'] === 'high' ? 'danger' : ($ticket['priority'] === 'middle' ? 'warning' : 'info'); ?>">
                                        <?php echo esc_html($this->priorityLabels[$ticket['priority']] ?? ucfirst($ticket['priority'])); ?>
                                    </span>
                                    <span style="color:var(--text-m);font-size:13px;">
                                        <?php echo esc_html(human_time_diff(strtotime($ticket['updated_at']), current_time('timestamp')) . ' ' . __('ago', 'ai-seo-client')); ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="fyn-card">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Create Ticket', 'ai-seo-client'); ?></h3>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('create_support_ticket', 'support_ticket_nonce'); ?>
                    <div class="sseo-form-field">
                        <label for="ticket_subject"><?php esc_html_e('Subject', 'ai-seo-client'); ?></label>
                        <input type="text" name="ticket_subject" id="ticket_subject" class="fyn-input" required>
                    </div>
                    <div class="sseo-form-field">
                        <label for="ticket_priority"><?php esc_html_e('Priority', 'ai-seo-client'); ?></label>
                        <select name="ticket_priority" id="ticket_priority" class="fyn-input">
                            <option value="low"><?php esc_html_e('Low', 'ai-seo-client'); ?></option>
                            <option value="middle" selected><?php esc_html_e('Medium', 'ai-seo-client'); ?></option>
                            <option value="high"><?php esc_html_e('High', 'ai-seo-client'); ?></option>
                        </select>
                    </div>
                    <div class="sseo-form-field">
                        <label for="ticket_message"><?php esc_html_e('Message', 'ai-seo-client'); ?></label>
                        <textarea name="ticket_message" id="ticket_message" class="fyn-input" rows="6" required></textarea>
                    </div>
                    <div class="sseo-form-field">
                        <label for="ticket_screenshots"><?php esc_html_e('Screenshots', 'ai-seo-client'); ?></label>
                        <div class="sseo-file-drop">
                            <input type="file" name="ticket_screenshots[]" id="ticket_screenshots" multiple accept="image/*">
                            <p class="sseo-file-hint"><?php esc_html_e('You can select multiple files. Hold Ctrl/Cmd to select more than one.', 'ai-seo-client'); ?></p>
                        </div>
                    </div>
                    <?php submit_button(__('Submit Ticket', 'ai-seo-client'), 'primary', 'create_support_ticket'); ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render the chatbot on/off toggle card.
     */
    private function renderChatbotToggle(): void
    {
        $disabled = (bool) get_option('sseo_ai_chatbot_disabled', 0);
        $enabled = !$disabled;
        ?>
        <div class="fyn-card" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:20px;">
            <div>
                <h3 style="margin:0 0 6px 0;font-size:15px;font-weight:700;color:var(--text-h);"><?php esc_html_e('Support Chatbot', 'ai-seo-client'); ?></h3>
                <p style="margin:0;color:var(--text-m);font-size:13px;">
                    <?php esc_html_e('Show the chatbot widget in the bottom right of the dashboard and on post edit pages.', 'ai-seo-client'); ?>
                </p>
            </div>
            <form method="post" style="flex-shrink: 0;">
                <?php wp_nonce_field('toggle_chatbot', 'chatbot_toggle_nonce'); ?>
                <label style="display: inline-flex; align-items: center; gap: 10px; cursor: pointer; user-select: none;">
                    <span style="font-weight: 600; color: #374151;"><?php echo $enabled ? esc_html__('On', 'ai-seo-client') : esc_html__('Off', 'ai-seo-client'); ?></span>
                    <input type="checkbox" name="chatbot_enabled" value="1" <?php checked($enabled); ?> onchange="this.form.submit()" style="width: 20px; height: 20px; cursor: pointer;">
                    <input type="hidden" name="toggle_chatbot" value="1">
                </label>
            </form>
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
            <div class="fyn-card" style="background:var(--fyn-danger-bg,#fee2e2);color:var(--fyn-danger);padding:20px;">
                <?php esc_html_e('Could not load ticket.', 'ai-seo-client'); ?>
                <?php if (is_wp_error($result)): ?>
                    <p><?php echo esc_html($result->get_error_message()); ?></p>
                <?php endif; ?>
                <p><a href="<?php echo esc_url($this->pageUrl()); ?>" class="fyn-btn fyn-btn-outline"><?php esc_html_e('Back', 'ai-seo-client'); ?></a></p>
            </div>
            <?php
            return;
        }

        $ticket = $result['ticket'];

        // Mark staff replies as read when the ticket is viewed
        try {
            $this->dashboardAPI->markSupportTicketRead($ticketId);
        } catch (\Exception $e) {
            // Silently fail — don't block ticket viewing
        }
        ?>
        <style>
            .sseo-reply-list { margin: 20px 0; display: flex; flex-direction: column; gap: 16px; }
            .sseo-reply { padding: 20px; border-radius: 10px; border: 1px solid var(--card-border); }
            .sseo-reply.staff { background: var(--well); border-color: var(--fyn-blue); }
            .sseo-reply.customer { background: var(--card); border-color: var(--card-border); }
            .sseo-reply-header { display: flex; justify-content: space-between; margin-bottom: 10px; font-weight: 600; align-items: center; }
            .sseo-reply-header .sseo-reply-author { display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--text-h); }
            .sseo-reply-header .sseo-reply-author .sseo-reply-avatar { width: 28px; height: 28px; border-radius: 50%; background: var(--fyn-blue); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; }
            .sseo-reply.staff .sseo-reply-avatar { background: var(--fyn-purple); }
            .sseo-reply.customer .sseo-reply-avatar { background: var(--fyn-blue); }
            .sseo-reply-message { color: var(--text-b); line-height: 1.6; font-size: 14px; }
            .sseo-screenshots { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
            .sseo-screenshots a { display: inline-block; border: 1px solid var(--card-border); border-radius: 6px; overflow: hidden; }
            .sseo-screenshots img { max-width: 150px; max-height: 150px; display: block; }
            .sseo-reply-form { background: var(--well); border: 1px solid var(--card-border); border-radius: 10px; padding: 24px; margin-top: 20px; }
            .sseo-reply-form h3 { margin: 0 0 16px 0; font-size: 15px; font-weight: 700; color: var(--text-h); }
            .sseo-reply-form .sseo-form-field { margin-bottom: 16px; }
            .sseo-file-drop { border: 2px dashed var(--divide); border-radius: 8px; padding: 20px; text-align: center; transition: all .2s ease; cursor: pointer; }
            .sseo-file-drop:hover { border-color: var(--fyn-blue); background: var(--well); }
            .sseo-file-drop input[type="file"] { width: 100%; }
            .sseo-file-hint { font-size: 12px; color: var(--text-m); margin-top: 4px; }
        </style>

        <?php if (isset($_GET['created'])): ?>
            <div class="fyn-card" style="margin-bottom:16px;background:var(--fyn-success-bg,#d1fae5);color:var(--fyn-success);padding:14px 20px;">
                <strong><?php esc_html_e('Ticket created successfully.', 'ai-seo-client'); ?></strong>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['reply_sent'])): ?>
            <div class="fyn-card" style="margin-bottom:16px;background:var(--fyn-success-bg,#d1fae5);color:var(--fyn-success);padding:14px 20px;">
                <strong><?php esc_html_e('Reply sent.', 'ai-seo-client'); ?></strong>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="fyn-card" style="margin-bottom:16px;background:var(--fyn-danger-bg,#fee2e2);color:var(--fyn-danger);padding:14px 20px;">
                <strong><?php echo esc_html(urldecode($_GET['error'])); ?></strong>
            </div>
        <?php endif; ?>

        <div class="fyn-card" style="margin-bottom:20px;">
            <h2 style="margin:0 0 12px;font-size:18px;font-weight:700;color:var(--text-h);"><?php echo esc_html($ticket['subject']); ?></h2>
            <div class="sseo-ticket-meta">
                <span class="fyn-badge fyn-badge--<?php echo $ticket['status'] === 'closed' ? 'success' : ($ticket['status'] === 'reaction' ? 'warning' : 'info'); ?>">
                    <?php echo esc_html($this->statusLabels[$ticket['status']] ?? ucfirst($ticket['status'])); ?>
                </span>
                <span class="fyn-badge fyn-badge--<?php echo $ticket['priority'] === 'high' ? 'danger' : ($ticket['priority'] === 'middle' ? 'warning' : 'info'); ?>">
                    <?php echo esc_html($this->priorityLabels[$ticket['priority']] ?? ucfirst($ticket['priority'])); ?>
                </span>
                <span style="color:var(--text-m);font-size:13px;">
                    <?php echo esc_html(sprintf(__('Created %s', 'ai-seo-client'), $ticket['created_at'])); ?>
                </span>
            </div>
            <hr style="border:none;border-top:1px solid var(--divide);margin:20px 0;">
            <p style="color:var(--text-b);"><?php echo nl2br(esc_html($ticket['message'])); ?></p>
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

        <div class="fyn-card">
            <div class="fyn-card-header">
                <span class="dashicons dashicons-format-chat"></span>
                <h3 class="fyn-card-title"><?php esc_html_e('Replies', 'ai-seo-client'); ?></h3>
            </div>
            <?php if (empty($ticket['replies'])): ?>
                <p><?php esc_html_e('No replies yet.', 'ai-seo-client'); ?></p>
            <?php else: ?>
                <div class="sseo-reply-list">
                    <?php foreach ($ticket['replies'] as $reply): ?>
                        <div class="sseo-reply <?php echo $reply['is_staff'] ? 'staff' : 'customer'; ?>">
                            <div class="sseo-reply-header">
                                <span class="sseo-reply-author">
                                    <span class="sseo-reply-avatar"><?php echo $reply['is_staff'] ? esc_html(mb_substr($reply['author_name'] ?: 'S', 0, 1)) : esc_html__('U', 'ai-seo-client'); ?></span>
                                    <?php echo $reply['is_staff'] ? esc_html($reply['author_name'] ?: __('Support', 'ai-seo-client')) : esc_html(__('You', 'ai-seo-client')); ?>
                                </span>
                                <small style="color: #6b7280; font-weight: 400;"><?php echo esc_html($reply['created_at']); ?></small>
                            </div>
                            <div class="sseo-reply-message"><?php echo nl2br(esc_html($reply['message'])); ?></div>
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
                <div class="sseo-reply-form">
                    <h3><?php esc_html_e('Add Reply', 'ai-seo-client'); ?></h3>
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('add_support_reply', 'support_reply_nonce'); ?>
                        <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['id']; ?>">
                        <div class="sseo-form-field">
                            <label for="reply_message"><?php esc_html_e('Message', 'ai-seo-client'); ?></label>
                            <textarea name="reply_message" id="reply_message" class="fyn-input" rows="5" required placeholder="<?php esc_attr_e('Type your reply here...', 'ai-seo-client'); ?>"></textarea>
                        </div>
                        <div class="sseo-form-field">
                            <label for="reply_screenshots"><?php esc_html_e('Screenshots', 'ai-seo-client'); ?></label>
                            <div class="sseo-file-drop">
                                <input type="file" name="reply_screenshots[]" id="reply_screenshots" multiple accept="image/*">
                                <p class="sseo-file-hint"><?php esc_html_e('You can select multiple files. Hold Ctrl/Cmd to select more than one.', 'ai-seo-client'); ?></p>
                            </div>
                        </div>
                        <?php submit_button(__('Send Reply', 'ai-seo-client'), 'primary', 'add_support_reply'); ?>
                    </form>
                </div>
            <?php else: ?>
                <p style="color:var(--text-m);"><em><?php esc_html_e('This ticket is closed. Create a new ticket if you need further assistance.', 'ai-seo-client'); ?></em></p>
            <?php endif; ?>

            <p style="margin-top: 20px;">
                <a href="<?php echo esc_url($this->pageUrl()); ?>" class="fyn-btn fyn-btn-outline">&larr; <?php esc_html_e('Back to tickets', 'ai-seo-client'); ?></a>
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

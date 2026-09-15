<?php

namespace SSEOAISaaS;

/**
 * Feedback Admin Interface
 *
 * Adds a "Feedback" page to the SaaS dashboard where staff can view,
 * filter and manage feedback submitted by client sites.
 */
class FeedbackAdmin
{
    private Feedback $feedback;

    private array $categoryLabels = [
        'bug' => 'Bug',
        'feature_request' => 'Feature request',
        'compliment' => 'Compliment',
        'question' => 'Vraag',
        'general' => 'Algemeen',
    ];

    private array $statusLabels = [
        'new' => 'New',
        'reviewed' => 'Reviewed',
        'resolved' => 'Resolved',
        'archived' => 'Archived',
    ];

    public function __construct(Feedback $feedback)
    {
        $this->feedback = $feedback;
    }

    public function register(): void
    {
        add_submenu_page(
            'sseo-ai-licenses',
            __('Feedback', 'sseo-ai-saas'),
            __('Feedback', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-feedback',
            [$this, 'renderFeedbackPage']
        );
    }

    public function renderFeedbackPage(): void
    {
        $this->processActions();

        $feedbackId = isset($_GET['feedback_id']) ? (int)$_GET['feedback_id'] : 0;

        if ($feedbackId > 0) {
            $this->renderFeedbackDetail($feedbackId);
            return;
        }

        $this->renderFeedbackList();
    }

    private function processActions(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'sseo-ai-saas'));
        }

        if (isset($_POST['sseo_ai_update_feedback']) && isset($_POST['sseo_ai_feedback_nonce'])) {
            if (!wp_verify_nonce($_POST['sseo_ai_feedback_nonce'], 'sseo_ai_update_feedback')) {
                wp_die(__('Security check failed.', 'sseo-ai-saas'));
            }

            $feedbackId = (int)($_POST['feedback_id'] ?? 0);
            $status = sanitize_text_field($_POST['feedback_status'] ?? '');

            if ($feedbackId > 0) {
                $this->feedback->updateFeedbackStatus($feedbackId, $status);
                wp_redirect(admin_url('admin.php?page=sseo-ai-feedback&feedback_id=' . $feedbackId . '&updated=1'));
                exit;
            }
        }
    }

    private function renderFeedbackList(): void
    {
        $filters = [
            'category' => sanitize_text_field($_GET['category'] ?? ''),
            'status' => sanitize_text_field($_GET['status'] ?? ''),
            'search' => sanitize_text_field($_GET['search'] ?? ''),
        ];

        $entries = $this->feedback->getAllFeedback(array_filter($filters));

        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Feedback', 'sseo-ai-saas'); ?></h1>

            <div class="sseo-ai-card">
                <form method="get" style="margin-bottom: 0;">
                    <input type="hidden" name="page" value="sseo-ai-feedback">
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                        <div>
                            <label for="category-filter"><?php esc_html_e('Categorie', 'sseo-ai-saas'); ?></label><br>
                            <select name="category" id="category-filter">
                                <option value=""><?php esc_html_e('Alle categorieën', 'sseo-ai-saas'); ?></option>
                                <?php foreach ($this->categoryLabels as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($filters['category'], $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="status-filter"><?php esc_html_e('Status', 'sseo-ai-saas'); ?></label><br>
                            <select name="status" id="status-filter">
                                <option value=""><?php esc_html_e('Alle statussen', 'sseo-ai-saas'); ?></option>
                                <?php foreach ($this->statusLabels as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($filters['status'], $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex: 1; min-width: 200px;">
                            <label for="search-filter"><?php esc_html_e('Zoeken', 'sseo-ai-saas'); ?></label><br>
                            <input type="text" name="search" id="search-filter" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php esc_attr_e('Bericht, tenant...', 'sseo-ai-saas'); ?>" style="width: 100%;">
                        </div>
                        <div>
                            <button type="submit" class="button button-primary"><?php esc_html_e('Filter', 'sseo-ai-saas'); ?></button>
                            <a href="<?php echo admin_url('admin.php?page=sseo-ai-feedback'); ?>" class="button"><?php esc_html_e('Reset', 'sseo-ai-saas'); ?></a>
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
                            <th><?php esc_html_e('Categorie', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Bericht', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Datum', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Acties', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($entries)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;"><?php esc_html_e('Geen feedback gevonden.', 'sseo-ai-saas'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($entries as $entry): ?>
                                <tr>
                                    <td>#<?php echo (int)$entry['id']; ?></td>
                                    <td>
                                        <?php echo esc_html($entry['tenant_name'] ?? '—'); ?><br>
                                        <small><?php echo esc_html($entry['tenant_email'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <span class="sseo-badge cat-<?php echo esc_attr($entry['category']); ?>">
                                            <?php echo esc_html($this->categoryLabels[$entry['category']] ?? ucfirst($entry['category'])); ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 400px;">
                                        <?php echo esc_html(wp_trim_words($entry['message'], 12, '...')); ?>
                                        <?php if (!empty($entry['page_url'])): ?>
                                            <br><small style="color: #6b7280;"><?php echo esc_html(sprintf(__('Pagina: %s', 'sseo-ai-saas'), $entry['page_url'])); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="sseo-badge status-<?php echo esc_attr($entry['status']); ?>">
                                            <?php echo esc_html($this->statusLabels[$entry['status']] ?? ucfirst($entry['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($entry['created_at']); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=sseo-ai-feedback&feedback_id=' . (int)$entry['id']); ?>" class="button button-small">
                                            <?php esc_html_e('Bekijk', 'sseo-ai-saas'); ?>
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

    private function renderFeedbackDetail(int $feedbackId): void
    {
        $entry = $this->feedback->getFeedbackById($feedbackId);
        if (!$entry) {
            echo '<div class="wrap sseo-ai-license-admin"><div class="notice notice-error"><p>' . esc_html__('Feedback niet gevonden.', 'sseo-ai-saas') . '</p></div></div>';
            return;
        }

        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php echo esc_html(sprintf(__('Feedback #%d', 'sseo-ai-saas'), $entry['id'])); ?></h1>

            <?php if (isset($_GET['updated'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Feedback bijgewerkt.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>

            <div class="sseo-ai-grid-2">
                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Details', 'sseo-ai-saas'); ?></h2>
                    <p><strong><?php esc_html_e('Tenant:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html($entry['tenant_name'] ?? '—'); ?> (<?php echo esc_html($entry['tenant_email'] ?? ''); ?>)</p>
                    <p><strong><?php esc_html_e('Domein:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html($entry['tenant_domain'] ?? '—'); ?></p>
                    <p><strong><?php esc_html_e('Licentie:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html($entry['tenant_license'] ?? '—'); ?></p>
                    <p><strong><?php esc_html_e('Categorie:', 'sseo-ai-saas'); ?></strong>
                        <span class="sseo-badge cat-<?php echo esc_attr($entry['category']); ?>">
                            <?php echo esc_html($this->categoryLabels[$entry['category']] ?? ucfirst($entry['category'])); ?>
                        </span>
                    </p>
                    <p><strong><?php esc_html_e('Aangemaakt:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html($entry['created_at']); ?></p>
                    <?php if (!empty($entry['page_url'])): ?>
                        <p><strong><?php esc_html_e('Pagina:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html($entry['page_url']); ?></p>
                    <?php endif; ?>

                    <form method="post">
                        <?php wp_nonce_field('sseo_ai_update_feedback', 'sseo_ai_feedback_nonce'); ?>
                        <input type="hidden" name="feedback_id" value="<?php echo (int)$entry['id']; ?>">
                        <table class="form-table">
                            <tr>
                                <th><label for="feedback_status"><?php esc_html_e('Status', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <select name="feedback_status" id="feedback_status">
                                        <?php foreach ($this->statusLabels as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($entry['status'], $value); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(__('Bijwerken', 'sseo-ai-saas'), 'primary', 'sseo_ai_update_feedback'); ?>
                    </form>
                </div>

                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Bericht', 'sseo-ai-saas'); ?></h2>
                    <div style="background: #f9fafb; padding: 15px; border-radius: 8px;">
                        <p><?php echo nl2br(esc_html($entry['message'])); ?></p>
                    </div>
                    <?php if (!empty($entry['screenshots'])): ?>
                        <h3><?php esc_html_e('Schermafbeeldingen', 'sseo-ai-saas'); ?></h3>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <?php foreach ($entry['screenshots'] as $url): ?>
                                <a href="<?php echo esc_url($url); ?>" target="_blank" style="display: inline-block; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden;">
                                    <img src="<?php echo esc_url($url); ?>" style="max-width: 150px; max-height: 150px; display: block;">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <p>
                <a href="<?php echo admin_url('admin.php?page=sseo-ai-feedback'); ?>" class="button">&larr; <?php esc_html_e('Terug naar feedback', 'sseo-ai-saas'); ?></a>
            </p>
        </div>
        <?php
    }
}

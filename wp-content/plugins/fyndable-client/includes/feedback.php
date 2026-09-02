<?php

namespace SSEOAIClient;

/**
 * Feedback — client-side UI
 *
 * Renders the "Feedback" page in the dashboard shell. Lets customers
 * submit product feedback (category + message + screenshot) with the
 * current portal page URL automatically captured. Feedback is stored
 * centrally on the SaaS dashboard.
 */
class FeedbackPage
{
    private Settings $settings;
    private DashboardAPI $dashboardAPI;

    private string $pageSlug = 'ai-seo-feedback';

    private array $categoryLabels;

    public function __construct(Settings $settings, DashboardAPI $dashboardAPI)
    {
        $this->settings = $settings;
        $this->dashboardAPI = $dashboardAPI;

        $this->categoryLabels = [
            'bug' => __('Bug', 'ai-seo-client'),
            'feature_request' => __('Feature request', 'ai-seo-client'),
            'compliment' => __('Compliment', 'ai-seo-client'),
            'question' => __('Vraag', 'ai-seo-client'),
            'general' => __('Algemeen', 'ai-seo-client'),
        ];
    }

    /**
     * Render the feedback page.
     */
    public function renderPage(): void
    {
        $this->processPostActions();
        ?>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Feedback', 'ai-seo-client'); ?></h1>
            </div>
            <div class="sseo-ai-content">
                <?php $this->renderFeedbackList(); ?>
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

        if (isset($_POST['submit_feedback']) && isset($_POST['feedback_nonce'])) {
            if (!wp_verify_nonce($_POST['feedback_nonce'], 'submit_feedback')) {
                wp_die(__('Security check failed.', 'ai-seo-client'));
            }

            if (!current_user_can('manage_options')) {
                wp_die(__('Insufficient permissions.', 'ai-seo-client'));
            }

            $category = sanitize_text_field($_POST['feedback_category'] ?? 'general');
            $message = sanitize_textarea_field($_POST['feedback_message'] ?? '');
            $pageUrl = sanitize_text_field($_POST['feedback_page_url'] ?? '');

            if (empty($message)) {
                return;
            }

            if (!in_array($category, ['bug', 'feature_request', 'compliment', 'question', 'general'], true)) {
                $category = 'general';
            }

            $screenshots = [];
            if (!empty($_FILES['feedback_screenshots']['tmp_name'][0])) {
                $screenshots = $this->uploadScreenshots($_FILES['feedback_screenshots']);
            }

            $result = $this->dashboardAPI->createFeedback($category, $message, $pageUrl, $screenshots);

            if (is_wp_error($result)) {
                $this->redirectWithMessage('error=' . urlencode($result->get_error_message()));
                return;
            }

            $this->redirectWithMessage('feedback_sent=1');
            return;
        }
    }

    /**
     * Redirect back to the feedback page preserving the iframe parameter.
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
     * Determine the current portal page for automatic capture.
     */
    private function getCurrentPortalPage(): string
    {
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        if (empty($page)) {
            return '';
        }

        $labelMap = [
            'ai-seo-dashboard' => 'Dashboard',
            'ai-seo-keywords' => 'Keywords',
            'ai-seo-ideas' => 'Ideas',
            'ai-seo-topic-clusters' => 'Topic Clusters',
            'ai-seo-content-calendar' => 'Content Calendar',
            'ai-seo-created-posts' => 'Created Posts',
            'ai-seo-competitor-research' => 'Competitors',
            'ai-seo-rank-tracker' => 'Rank Tracker',
            'ai-seo-site-audit' => 'Site Audit',
            'ai-seo-link-manager' => 'Link Manager',
            'ai-seo-bulk' => 'Bulk Optimizer',
            'ai-seo-sitemaps' => 'Sitemaps',
            'ai-seo-data-dashboard' => 'SEO Data',
            'ai-seo-google-data' => 'Google Data',
            'ai-seo-llm-tracker' => 'LLM Tracker',
            'ai-seo-ab-testing' => 'A/B Testing',
            'ai-seo-ai-tools' => 'AI Tools',
            'ai-seo-integrations' => 'Integrations',
            'ai-seo-support' => 'Support',
            'ai-seo-feedback' => 'Feedback',
            'ai-seo-settings' => 'Settings',
            'ai-seo-client' => 'Connection',
        ];

        $label = $labelMap[$page] ?? $page;
        return $label;
    }

    /**
     * Render the feedback list and create form.
     */
    private function renderFeedbackList(): void
    {
        $feedback = $this->dashboardAPI->getFeedback();
        $hasError = is_wp_error($feedback);
        $errorMessage = $hasError ? $feedback->get_error_message() : '';
        $feedbackList = (!$hasError && !empty($feedback['feedback'])) ? $feedback['feedback'] : [];
        $currentPageLabel = $this->getCurrentPortalPage();
        ?>
        <style>
            .sseo-feedback-grid { display: grid; grid-template-columns: 1fr 380px; gap: 30px; }
            .sseo-feedback-card { background: rgba(255,255,255,0.95); border-radius: 12px; padding: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); }
            .sseo-feedback-card h2 { margin-top: 0; font-size: 20px; }
            .sseo-form-field { margin-bottom: 18px; }
            .sseo-form-field label { display: block; font-weight: 600; margin-bottom: 6px; color: #374151; }
            .sseo-form-field input[type="text"],
            .sseo-form-field textarea,
            .sseo-form-field select { width: 100%; padding: 10px 14px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
            .sseo-form-field input:focus,
            .sseo-form-field textarea:focus,
            .sseo-form-field select:focus { border-color: #379fd3; outline: none; box-shadow: 0 0 0 3px rgba(55,159,211,.1); }
            .sseo-feedback-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 16px; }
            .sseo-feedback-item { border: 2px solid #e5e7eb; border-radius: 10px; padding: 20px 24px; transition: all .2s ease; }
            .sseo-feedback-item:hover { border-color: #379fd3; box-shadow: 0 4px 12px rgba(55,159,211,.1); transform: translateY(-1px); }
            .sseo-feedback-meta { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; align-items: center; }
            .sseo-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
            .sseo-badge.cat-bug { background: #fee2e2; color: #991b1b; }
            .sseo-badge.cat-feature_request { background: #e8f4fa; color: #1e40af; }
            .sseo-badge.cat-compliment { background: #d1fae5; color: #065f46; }
            .sseo-badge.cat-question { background: #fef3c7; color: #92400e; }
            .sseo-badge.cat-general { background: #f3f4f6; color: #374151; }
            .sseo-badge.status-new { background: #dbeafe; color: #1e40af; }
            .sseo-badge.status-reviewed { background: #f3f4f6; color: #6b7280; }
            .sseo-feedback-page { font-size: 12px; color: #6b7280; margin-top: 6px; }
            .sseo-feedback-message { color: #374151; line-height: 1.6; font-size: 14px; margin-top: 8px; }
            .sseo-screenshots { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
            .sseo-screenshots a { display: inline-block; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; }
            .sseo-screenshots img { max-width: 150px; max-height: 150px; display: block; }
            .sseo-file-hint { font-size: 12px; color: #6b7280; margin-top: 4px; }
            .sseo-file-drop { border: 2px dashed #d1d5db; border-radius: 8px; padding: 20px; text-align: center; transition: all .2s ease; cursor: pointer; }
            .sseo-file-drop:hover { border-color: #379fd3; background: #e8f4fa; }
            .sseo-file-drop input[type="file"] { width: 100%; }
            .sseo-current-page-hint { font-size: 12px; color: #6b7280; margin-top: 4px; }
            @media (max-width: 900px) { .sseo-feedback-grid { grid-template-columns: 1fr; } }
        </style>

        <?php if (isset($_GET['feedback_sent'])): ?>
            <div class="sseo-feedback-card" style="margin-bottom: 30px; background: #d1fae5; color: #065f46;">
                <strong><?php esc_html_e('Bedankt voor je feedback! Je feedback is succesvol verzonden.', 'ai-seo-client'); ?></strong>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="sseo-feedback-card" style="margin-bottom: 30px; background: #fee2e2; color: #991b1b;">
                <strong><?php echo esc_html(urldecode($_GET['error'])); ?></strong>
            </div>
        <?php endif; ?>

        <?php if ($hasError): ?>
            <div class="sseo-feedback-card" style="margin-bottom: 30px; background: #fee2e2; color: #991b1b;">
                <strong><?php echo esc_html($errorMessage); ?></strong>
            </div>
        <?php endif; ?>

        <div class="sseo-feedback-grid">
            <div class="sseo-feedback-card">
                <h2><?php esc_html_e('Jouw feedback', 'ai-seo-client'); ?></h2>
                <?php if (empty($feedbackList) && !$hasError): ?>
                    <p><?php esc_html_e('Je hebt nog geen feedback ingediend.', 'ai-seo-client'); ?></p>
                <?php elseif (!empty($feedbackList)): ?>
                    <ul class="sseo-feedback-list">
                        <?php foreach ($feedbackList as $item): ?>
                            <li class="sseo-feedback-item">
                                <div class="sseo-feedback-meta">
                                    <span class="sseo-badge cat-<?php echo esc_attr($item['category']); ?>">
                                        <?php echo esc_html($this->categoryLabels[$item['category']] ?? ucfirst($item['category'])); ?>
                                    </span>
                                    <span class="sseo-badge status-<?php echo esc_attr($item['status']); ?>">
                                        <?php echo esc_html(ucfirst($item['status'])); ?>
                                    </span>
                                    <span style="color: #6b7280; font-size: 13px;">
                                        <?php echo esc_html(human_time_diff(strtotime($item['created_at']), current_time('timestamp')) . ' ' . __('geleden', 'ai-seo-client')); ?>
                                    </span>
                                </div>
                                <div class="sseo-feedback-message"><?php echo nl2br(esc_html($item['message'])); ?></div>
                                <?php if (!empty($item['page_url'])): ?>
                                    <div class="sseo-feedback-page">
                                        <?php echo esc_html(sprintf(__('Pagina: %s', 'ai-seo-client'), $item['page_url'])); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($item['screenshots'])): ?>
                                    <div class="sseo-screenshots">
                                        <?php foreach ($item['screenshots'] as $url): ?>
                                            <a href="<?php echo esc_url($url); ?>" target="_blank">
                                                <img src="<?php echo esc_url($url); ?>" alt="<?php esc_attr_e('Screenshot', 'ai-seo-client'); ?>">
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="sseo-feedback-card">
                <h2><?php esc_html_e('Feedback geven', 'ai-seo-client'); ?></h2>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('submit_feedback', 'feedback_nonce'); ?>
                    <input type="hidden" name="feedback_page_url" value="<?php echo esc_attr($currentPageLabel); ?>">
                    <?php if ($currentPageLabel): ?>
                        <div class="sseo-form-field">
                            <p class="sseo-current-page-hint">
                                <?php echo esc_html(sprintf(__('Huidige pagina: %s (wordt automatisch meegestuurd)', 'ai-seo-client'), $currentPageLabel)); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    <div class="sseo-form-field">
                        <label for="feedback_category"><?php esc_html_e('Categorie', 'ai-seo-client'); ?></label>
                        <select name="feedback_category" id="feedback_category">
                            <option value="bug"><?php esc_html_e('Bug', 'ai-seo-client'); ?></option>
                            <option value="feature_request"><?php esc_html_e('Feature request', 'ai-seo-client'); ?></option>
                            <option value="compliment"><?php esc_html_e('Compliment', 'ai-seo-client'); ?></option>
                            <option value="question"><?php esc_html_e('Vraag', 'ai-seo-client'); ?></option>
                            <option value="general"><?php esc_html_e('Algemeen', 'ai-seo-client'); ?></option>
                        </select>
                    </div>
                    <div class="sseo-form-field">
                        <label for="feedback_message"><?php esc_html_e('Bericht', 'ai-seo-client'); ?></label>
                        <textarea name="feedback_message" id="feedback_message" rows="6" required placeholder="<?php esc_attr_e('Deel je feedback hier...', 'ai-seo-client'); ?>"></textarea>
                    </div>
                    <div class="sseo-form-field">
                        <label for="feedback_screenshots"><?php esc_html_e('Schermafbeeldingen', 'ai-seo-client'); ?></label>
                        <div class="sseo-file-drop">
                            <input type="file" name="feedback_screenshots[]" id="feedback_screenshots" multiple accept="image/*">
                            <p class="sseo-file-hint"><?php esc_html_e('Je kunt meerdere bestanden selecteren. Houd Ctrl/Cmd ingedrukt om er meer te kiezen.', 'ai-seo-client'); ?></p>
                        </div>
                    </div>
                    <?php submit_button(__('Feedback versturen', 'ai-seo-client'), 'primary', 'submit_feedback'); ?>
                </form>
            </div>
        </div>
        <?php
    }
}

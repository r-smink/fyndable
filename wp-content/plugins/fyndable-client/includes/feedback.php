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
            'question' => __('Question', 'ai-seo-client'),
            'general' => __('General', 'ai-seo-client'),
        ];
    }

    /**
     * Render the feedback page.
     */
    public function renderPage(): void
    {
        $this->processPostActions();
        ?>
        <div class="wrap fyn-animate-in" style="max-width:1440px;margin:0 auto;padding:28px 32px 48px;">
            <div class="fyn-page-header">
                <div class="fyn-page-header-left">
                    <div class="fyn-breadcrumb">Fyndable &rsaquo; <span class="fyn-crumb"><?php esc_html_e('Feedback', 'ai-seo-client'); ?></span></div>
                    <h1><?php esc_html_e('Feedback', 'ai-seo-client'); ?></h1>
                    <p class="fyn-page-desc"><?php esc_html_e('Send us your feedback or view previous submissions.', 'ai-seo-client'); ?></p>
                </div>
            </div>
            <?php $this->renderFeedbackList(); ?>
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
            .sseo-feedback-grid { display: grid; grid-template-columns: 1fr 380px; gap: 20px; }
            .sseo-form-field { margin-bottom: 18px; }
            .sseo-form-field label { display: block; font-weight: 600; margin-bottom: 6px; color: var(--text-h); }
            .sseo-form-field input[type="text"],
            .sseo-form-field textarea,
            .sseo-form-field select { width: 100%; padding: 10px 14px; border: 1px solid var(--input-border); border-radius: 9px; font-size: 14px; background: var(--input-bg); color: var(--text-b); }
            .sseo-form-field input:focus,
            .sseo-form-field textarea:focus,
            .sseo-form-field select:focus { border-color: var(--fyn-blue); outline: none; box-shadow: 0 0 0 3px rgba(55,159,211,.1); }
            .sseo-feedback-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 16px; }
            .sseo-feedback-item { border: 1px solid var(--card-border); border-radius: 10px; padding: 20px 24px; transition: all .2s ease; background: var(--card); }
            .sseo-feedback-item:hover { border-color: var(--fyn-blue); box-shadow: var(--card-shadow); transform: translateY(-1px); }
            .sseo-feedback-meta { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; align-items: center; }
            .sseo-feedback-page { font-size: 12px; color: var(--text-m); margin-top: 6px; }
            .sseo-feedback-message { color: var(--text-b); line-height: 1.6; font-size: 14px; margin-top: 8px; }
            .sseo-screenshots { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
            .sseo-screenshots a { display: inline-block; border: 1px solid var(--card-border); border-radius: 6px; overflow: hidden; }
            .sseo-screenshots img { max-width: 150px; max-height: 150px; display: block; }
            .sseo-file-hint { font-size: 12px; color: var(--text-m); margin-top: 4px; }
            .sseo-file-drop { border: 2px dashed var(--divide); border-radius: 8px; padding: 20px; text-align: center; transition: all .2s ease; cursor: pointer; }
            .sseo-file-drop:hover { border-color: var(--fyn-blue); background: var(--well); }
            .sseo-file-drop input[type="file"] { width: 100%; }
            .sseo-current-page-hint { font-size: 12px; color: var(--text-m); margin-top: 4px; }
            @media (max-width: 900px) { .sseo-feedback-grid { grid-template-columns: 1fr; } }
        </style>

        <?php if (isset($_GET['feedback_sent'])): ?>
            <div class="fyn-card" style="margin-bottom:20px;background:var(--fyn-success-bg,#d1fae5);color:var(--fyn-success);">
                <strong><?php esc_html_e('Thank you for your feedback! Your feedback has been submitted successfully.', 'ai-seo-client'); ?></strong>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="fyn-card" style="margin-bottom:20px;background:var(--fyn-danger-bg,#fee2e2);color:var(--fyn-danger);">
                <strong><?php echo esc_html(urldecode($_GET['error'])); ?></strong>
            </div>
        <?php endif; ?>

        <?php if ($hasError): ?>
            <div class="fyn-card" style="margin-bottom:20px;background:var(--fyn-danger-bg,#fee2e2);color:var(--fyn-danger);">
                <strong><?php echo esc_html($errorMessage); ?></strong>
            </div>
        <?php endif; ?>

        <div class="sseo-feedback-grid">
            <div class="fyn-card">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-format-chat"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Your Feedback', 'ai-seo-client'); ?></h3>
                </div>
                <?php if (empty($feedbackList) && !$hasError): ?>
                    <p><?php esc_html_e('You have not submitted any feedback yet.', 'ai-seo-client'); ?></p>
                <?php elseif (!empty($feedbackList)): ?>
                    <ul class="sseo-feedback-list">
                        <?php foreach ($feedbackList as $item): ?>
                            <li class="sseo-feedback-item">
                                <div class="sseo-feedback-meta">
                                    <span class="fyn-badge fyn-badge--<?php echo esc_attr($item['category'] === 'bug' ? 'danger' : ($item['category'] === 'compliment' ? 'success' : ($item['category'] === 'question' ? 'warning' : 'info'))); ?>">
                                        <?php echo esc_html($this->categoryLabels[$item['category']] ?? ucfirst($item['category'])); ?>
                                    </span>
                                    <span class="fyn-badge fyn-badge--<?php echo esc_attr($item['status'] === 'new' ? 'info' : 'default'); ?>">
                                        <?php echo esc_html(ucfirst($item['status'])); ?>
                                    </span>
                                    <span style="color:var(--text-m);font-size:13px;">
                                        <?php echo esc_html(human_time_diff(strtotime($item['created_at']), current_time('timestamp')) . ' ' . __('ago', 'ai-seo-client')); ?>
                                    </span>
                                </div>
                                <div class="sseo-feedback-message"><?php echo nl2br(esc_html($item['message'])); ?></div>
                                <?php if (!empty($item['page_url'])): ?>
                                    <div class="sseo-feedback-page">
                                        <?php echo esc_html(sprintf(__('Page: %s', 'ai-seo-client'), $item['page_url'])); ?>
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

            <div class="fyn-card">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-edit"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Submit Feedback', 'ai-seo-client'); ?></h3>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('submit_feedback', 'feedback_nonce'); ?>
                    <input type="hidden" name="feedback_page_url" value="<?php echo esc_attr($currentPageLabel); ?>">
                    <?php if ($currentPageLabel): ?>
                        <div class="sseo-form-field">
                            <p class="sseo-current-page-hint">
                                <?php echo esc_html(sprintf(__('Current page: %s (sent automatically)', 'ai-seo-client'), $currentPageLabel)); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    <div class="sseo-form-field">
                        <label for="feedback_category"><?php esc_html_e('Category', 'ai-seo-client'); ?></label>
                        <select name="feedback_category" id="feedback_category">
                            <option value="bug"><?php esc_html_e('Bug', 'ai-seo-client'); ?></option>
                            <option value="feature_request"><?php esc_html_e('Feature request', 'ai-seo-client'); ?></option>
                            <option value="compliment"><?php esc_html_e('Compliment', 'ai-seo-client'); ?></option>
                            <option value="question"><?php esc_html_e('Question', 'ai-seo-client'); ?></option>
                            <option value="general"><?php esc_html_e('General', 'ai-seo-client'); ?></option>
                        </select>
                    </div>
                    <div class="sseo-form-field">
                        <label for="feedback_message"><?php esc_html_e('Message', 'ai-seo-client'); ?></label>
                        <textarea name="feedback_message" id="feedback_message" rows="6" required placeholder="<?php esc_attr_e('Share your feedback here...', 'ai-seo-client'); ?>"></textarea>
                    </div>
                    <div class="sseo-form-field">
                        <label for="feedback_screenshots"><?php esc_html_e('Screenshots', 'ai-seo-client'); ?></label>
                        <div class="sseo-file-drop">
                            <input type="file" name="feedback_screenshots[]" id="feedback_screenshots" multiple accept="image/*">
                            <p class="sseo-file-hint"><?php esc_html_e('You can select multiple files. Hold Ctrl/Cmd to select more.', 'ai-seo-client'); ?></p>
                        </div>
                    </div>
                    <?php submit_button(__('Submit Feedback', 'ai-seo-client'), 'primary', 'submit_feedback'); ?>
                </form>
            </div>
        </div>
        <?php
    }
}

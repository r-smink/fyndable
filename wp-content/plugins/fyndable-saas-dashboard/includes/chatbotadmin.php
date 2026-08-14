<?php

namespace SSEOAISaaS;

/**
 * Chatbot Admin — SaaS dashboard settings page for the client support chatbot.
 *
 * Lets administrators configure:
 *   - chatbot enabled/disabled
 *   - chatbot display name
 *   - chatbot avatar/profile image (uploaded via wp_handle_upload)
 *   - additional knowledge content (Markdown or JSON, editable textarea)
 *   - small recent-history overview (read-only)
 *
 * All settings are stored as global wp_options and synchronized to clients
 * via the tenant/status response (see LicenseAPI).
 */
class ChatbotAdmin
{
    private const OPTION_KEY = 'sseo_ai_saas_chatbot_config';
    private const HISTORY_OPTION_KEY = 'sseo_ai_saas_chatbot_history';
    private const HISTORY_LIMIT = 50;

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu'], 20);
        add_action('admin_init', [$this, 'checkPagePermission'], 5);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_post_sseo_ai_chatbot_upload_avatar', [$this, 'handleAvatarUpload']);
        add_action('admin_post_sseo_ai_chatbot_upload_knowledge', [$this, 'handleKnowledgeUpload']);
        add_action('admin_post_sseo_ai_chatbot_clear_history', [$this, 'handleClearHistory']);
    }

    public function checkPagePermission(): void
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'sseo-ai-chatbot') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission.', 'sseo-ai-saas'));
        }
    }

    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, 'sseo-ai-chatbot') === false) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'sseo-ai-saas-admin',
            plugins_url('assets/license-admin.css', SSEO_AI_SAAS_PLUGIN_FILE),
            [],
            SSEO_AI_SAAS_VERSION
        );

        wp_enqueue_style(
            'sseo-ai-chatbot-admin',
            plugins_url('assets/chatbot-admin.css', SSEO_AI_SAAS_PLUGIN_FILE),
            ['sseo-ai-saas-admin'],
            SSEO_AI_SAAS_VERSION
        );

        wp_enqueue_script(
            'sseo-ai-chatbot-admin',
            plugins_url('assets/chatbot-admin.js', SSEO_AI_SAAS_PLUGIN_FILE),
            ['jquery'],
            SSEO_AI_SAAS_VERSION,
            true
        );

        wp_localize_script('sseo-ai-chatbot-admin', 'ChatbotAdmin', [
            'avatarModalTitle' => __('Select chatbot avatar', 'sseo-ai-saas'),
            'avatarModalButton' => __('Use as avatar', 'sseo-ai-saas'),
        ]);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'sseo-ai-licenses',
            __('Chatbot Settings', 'sseo-ai-saas'),
            __('Chatbot', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-chatbot',
            [$this, 'renderPage']
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            'sseo_ai_saas_chatbot',
            self::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeConfig'],
                'default' => [],
            ]
        );
    }

    /**
     * Sanitize the chatbot config before saving.
     */
    public function sanitizeConfig(array $input): array
    {
        $current = $this->getConfig();

        return [
            'enabled' => !empty($input['enabled']) ? 1 : 0,
            'name' => sanitize_text_field($input['name'] ?? $current['name'] ?? 'Fyndable Assistant'),
            'avatar_url' => esc_url_raw($input['avatar_url'] ?? $current['avatar_url'] ?? ''),
            'knowledge' => $this->sanitizeKnowledge($input['knowledge'] ?? $current['knowledge'] ?? ''),
            'updated_at' => time(),
        ];
    }

    /**
     * Sanitize knowledge content (Markdown or JSON).
     * Validates JSON if it looks like JSON; otherwise treats as Markdown.
     */
    private function sanitizeKnowledge(string $raw): string
    {
        $raw = wp_kses_post($raw);
        $trimmed = trim($raw);

        if ($trimmed === '') {
            return '';
        }

        // If it starts with { or [, try to validate JSON
        if ($trimmed[0] === '{' || $trimmed[0] === '[') {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Invalid JSON — keep as-is but flag (admin will see it)
                return $raw;
            }
            // Re-encode pretty for storage
            return wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return $raw;
    }

    /**
     * Get the current chatbot config.
     */
    public function getConfig(): array
    {
        $defaults = [
            'enabled' => 1,
            'name' => 'Fyndable Assistant',
            'avatar_url' => '',
            'knowledge' => '',
            'updated_at' => 0,
        ];

        $stored = get_option(self::OPTION_KEY, []);
        if (!is_array($stored)) {
            return $defaults;
        }

        return array_merge($defaults, $stored);
    }

    /**
     * Handle avatar upload via admin_post.
     */
    public function handleAvatarUpload(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission.', 'sseo-ai-saas'));
        }

        check_admin_referer('sseo_ai_chatbot_avatar');

        if (empty($_FILES['avatar'])) {
            wp_safe_redirect(admin_url('admin.php?page=sseo-ai-chatbot&error=no_file'));
            exit;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload = wp_handle_upload($_FILES['avatar'], [
            'test_form' => false,
            'mimes' => [
                'jpg|jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
            ],
        ]);

        if (isset($upload['error'])) {
            wp_safe_redirect(admin_url('admin.php?page=sseo-ai-chatbot&error=upload_failed'));
            exit;
        }

        $config = $this->getConfig();
        $config['avatar_url'] = $upload['url'];
        $config['updated_at'] = time();
        update_option(self::OPTION_KEY, $config);

        wp_safe_redirect(admin_url('admin.php?page=sseo-ai-chatbot&avatar=1'));
        exit;
    }

    /**
     * Handle knowledge file upload (.md or .json).
     */
    public function handleKnowledgeUpload(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission.', 'sseo-ai-saas'));
        }

        check_admin_referer('sseo_ai_chatbot_knowledge');

        if (empty($_FILES['knowledge_file'])) {
            wp_safe_redirect(admin_url('admin.php?page=sseo-ai-chatbot&error=no_file'));
            exit;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $upload = wp_handle_upload($_FILES['knowledge_file'], [
            'test_form' => false,
            'mimes' => [
                'md' => 'text/markdown',
                'json' => 'application/json',
            ],
        ]);

        if (isset($upload['error'])) {
            wp_safe_redirect(admin_url('admin.php?page=sseo-ai-chatbot&error=upload_failed'));
            exit;
        }

        $contents = file_get_contents($upload['file']);
        if ($contents === false) {
            wp_safe_redirect(admin_url('admin.php?page=sseo-ai-chatbot&error=read_failed'));
            exit;
        }

        // Limit size to 500KB to avoid bloating the option
        if (strlen($contents) > 512000) {
            wp_safe_redirect(admin_url('admin.php?page=sseo-ai-chatbot&error=too_large'));
            exit;
        }

        $config = $this->getConfig();
        $config['knowledge'] = $this->sanitizeKnowledge($contents);
        $config['updated_at'] = time();
        update_option(self::OPTION_KEY, $config);

        // Clean up the temp file
        @unlink($upload['file']);

        wp_safe_redirect(admin_url('admin.php?page=sseo-ai-chatbot&knowledge=1'));
        exit;
    }

    /**
     * Clear chatbot history.
     */
    public function handleClearHistory(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission.', 'sseo-ai-saas'));
        }

        check_admin_referer('sseo_ai_chatbot_clear_history');

        delete_option(self::HISTORY_OPTION_KEY);

        wp_safe_redirect(admin_url('admin.php?page=sseo-ai-chatbot&history_cleared=1'));
        exit;
    }

    /**
     * Log a chatbot interaction (called from LicenseAPI or REST when a client asks).
     */
    public static function logInteraction(array $entry): void
    {
        $history = get_option(self::HISTORY_OPTION_KEY, []);
        if (!is_array($history)) {
            $history = [];
        }

        $entry['timestamp'] = current_time('mysql');
        array_unshift($history, $entry);

        // Keep only the most recent HISTORY_LIMIT entries
        $history = array_slice($history, 0, self::HISTORY_LIMIT);

        update_option(self::HISTORY_OPTION_KEY, $history);
    }

    /**
     * Render the settings page.
     */
    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission.', 'sseo-ai-saas'));
        }

        $config = $this->getConfig();
        $history = get_option(self::HISTORY_OPTION_KEY, []);
        if (!is_array($history)) {
            $history = [];
        }

        $avatarUrl = $config['avatar_url'];
        $knowledge = $config['knowledge'];
        $botName = $config['name'];
        $enabled = $config['enabled'];

        ?>
        <div class="wrap sseo-ai-license-admin sseo-ai-chatbot-admin">
            <h1><?php echo esc_html__('Chatbot Settings', 'sseo-ai-saas'); ?></h1>
            <p><?php echo esc_html__('Configureer de support-chatbot die aan client-sites wordt gesynchroniseerd.', 'sseo-ai-saas'); ?></p>

            <?php if (isset($_GET['avatar'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Avatar bijgewerkt.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['knowledge'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Kennisbestand bijgewerkt.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['history_cleared'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Geschiedenis gewist.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($this->errorMessage($_GET['error'])); ?></p></div>
            <?php endif; ?>

            <div class="sseo-ai-chatbot-grid">
                <!-- General settings -->
                <div class="sseo-ai-chatbot-card">
                    <h2><?php echo esc_html__('Algemeen', 'sseo-ai-saas'); ?></h2>
                    <form method="post" action="options.php">
                        <?php settings_fields('sseo_ai_saas_chatbot'); ?>
                        <?php do_settings_sections('sseo_ai_saas_chatbot'); ?>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php echo esc_html__('Chatbot inschakelen', 'sseo-ai-saas'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enabled]" value="1" <?php checked($enabled, 1); ?>>
                                        <?php echo esc_html__('Toon chatbot aan clients', 'sseo-ai-saas'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Naam', 'sseo-ai-saas'); ?></th>
                                <td>
                                    <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[name]" value="<?php echo esc_attr($botName); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Avatar URL', 'sseo-ai-saas'); ?></th>
                                <td>
                                    <input type="text" id="sseo-ai-chatbot-avatar-url" name="<?php echo esc_attr(self::OPTION_KEY); ?>[avatar_url]" value="<?php echo esc_attr($avatarUrl); ?>" class="regular-text">
                                    <button type="button" class="button" id="sseo-ai-chatbot-pick-avatar"><?php echo esc_html__('Kies uit mediabibliotheek', 'sseo-ai-saas'); ?></button>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button(__('Opslaan', 'sseo-ai-saas')); ?>
                    </form>
                </div>

                <!-- Avatar preview + upload -->
                <div class="sseo-ai-chatbot-card">
                    <h2><?php echo esc_html__('Avatar', 'sseo-ai-saas'); ?></h2>
                    <div class="sseo-ai-chatbot-avatar-preview">
                        <?php if ($avatarUrl): ?>
                            <img src="<?php echo esc_url($avatarUrl); ?>" alt="" id="sseo-ai-chatbot-avatar-img">
                        <?php else: ?>
                            <div class="sseo-ai-chatbot-avatar-placeholder" id="sseo-ai-chatbot-avatar-placeholder"><?php echo esc_html(mb_substr($botName, 0, 2)); ?></div>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field('sseo_ai_chatbot_avatar'); ?>
                        <input type="hidden" name="action" value="sseo_ai_chatbot_upload_avatar">
                        <p>
                            <input type="file" name="avatar" accept="image/png,image/jpeg,image/gif,image/webp">
                        </p>
                        <p>
                            <button type="submit" class="button button-secondary"><?php echo esc_html__('Upload avatar', 'sseo-ai-saas'); ?></button>
                        </p>
                    </form>
                </div>

                <!-- Knowledge upload + editor -->
                <div class="sseo-ai-chatbot-card sseo-ai-chatbot-card-wide">
                    <h2><?php echo esc_html__('Kennisbestand', 'sseo-ai-saas'); ?></h2>
                    <p><?php echo esc_html__('Upload een .md of .json bestand of bewerk de inhoud direct. JSON moet een array met entries zijn (id, title, category, keywords[], answer). Markdown wordt per ## header gesplitst.', 'sseo-ai-saas'); ?></p>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="margin-bottom:12px;">
                        <?php wp_nonce_field('sseo_ai_chatbot_knowledge'); ?>
                        <input type="hidden" name="action" value="sseo_ai_chatbot_upload_knowledge">
                        <input type="file" name="knowledge_file" accept=".md,.json,text/markdown,application/json">
                        <button type="submit" class="button button-secondary"><?php echo esc_html__('Upload kennisbestand', 'sseo-ai-saas'); ?></button>
                    </form>

                    <form method="post" action="options.php">
                        <?php settings_fields('sseo_ai_saas_chatbot'); ?>
                        <input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enabled]" value="<?php echo esc_attr($enabled); ?>">
                        <input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[name]" value="<?php echo esc_attr($botName); ?>">
                        <input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[avatar_url]" value="<?php echo esc_attr($avatarUrl); ?>">
                        <p>
                            <textarea name="<?php echo esc_attr(self::OPTION_KEY); ?>[knowledge]" id="sseo-ai-chatbot-knowledge" rows="18" class="large-text code" style="font-family:monospace;"><?php echo esc_textarea($knowledge); ?></textarea>
                        </p>
                        <?php submit_button(__('Kennis opslaan', 'sseo-ai-saas')); ?>
                    </form>
                </div>

                <!-- History -->
                <div class="sseo-ai-chatbot-card sseo-ai-chatbot-card-wide">
                    <h2><?php echo esc_html__('Recente gesprekken', 'sseo-ai-saas'); ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;float:right;">
                            <?php wp_nonce_field('sseo_ai_chatbot_clear_history'); ?>
                            <input type="hidden" name="action" value="sseo_ai_chatbot_clear_history">
                            <button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(__('Geschiedenis wissen?', 'sseo-ai-saas')); ?>')"><?php echo esc_html__('Wissen', 'sseo-ai-saas'); ?></button>
                        </form>
                    </h2>
                    <?php if (empty($history)): ?>
                        <p><?php echo esc_html__('Nog geen gesprekken geregistreerd.', 'sseo-ai-saas'); ?></p>
                    <?php else: ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Tijd', 'sseo-ai-saas'); ?></th>
                                    <th><?php echo esc_html__('Client', 'sseo-ai-saas'); ?></th>
                                    <th><?php echo esc_html__('Vraag', 'sseo-ai-saas'); ?></th>
                                    <th><?php echo esc_html__('Bron', 'sseo-ai-saas'); ?></th>
                                    <th><?php echo esc_html__('Ticket', 'sseo-ai-saas'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($history, 0, 20) as $item): ?>
                                    <tr>
                                        <td><?php echo esc_html($item['timestamp'] ?? ''); ?></td>
                                        <td><?php echo esc_html($item['tenant'] ?? '—'); ?></td>
                                        <td><?php echo esc_html(mb_strimwidth($item['question'] ?? '', 0, 80, '...')); ?></td>
                                        <td><?php echo esc_html($item['source'] ?? '—'); ?></td>
                                        <td><?php echo !empty($item['ticket_id']) ? '#' . esc_html($item['ticket_id']) : '—'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function errorMessage(string $code): string
    {
        $messages = [
            'no_file' => __('Geen bestand geselecteerd.', 'sseo-ai-saas'),
            'upload_failed' => __('Upload mislukt. Controleer bestandstype en grootte.', 'sseo-ai-saas'),
            'read_failed' => __('Kon bestand niet lezen.', 'sseo-ai-saas'),
            'too_large' => __('Bestand is groter dan 500KB.', 'sseo-ai-saas'),
        ];
        return $messages[$code] ?? __('Onbekende fout.', 'sseo-ai-saas');
    }
}

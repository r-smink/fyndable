<?php

namespace SSEOAIClient;

/**
 * Unified Post Meta Box with Grouped Accordion
 *
 * Replaces 20+ individual meta boxes on the post edit screen
 * with a single "Fyndable Smart SEO" meta box containing grouped
 * accordion sections with custom branding.
 */
class PostMetaBox
{
    private array $groups = [];
    private array $items = [];

    /**
     * Define a group (accordion section).
     *
     * @param string $id     Group identifier
     * @param string $label  Section title
     * @param string $icon   Dashicon class (e.g. 'dashicons-edit'). Raw HTML
     *                       (an entity/emoji) is still accepted for back-compat.
     * @param string $accent Section accent colour (hex) for the left border and icon.
     */
    public function addGroup(string $id, string $label, string $icon = '', string $accent = ''): void
    {
        $this->groups[$id] = [
            'id' => $id,
            'label' => $label,
            'icon' => $icon,
            'accent' => $accent,
            'items' => [],
        ];
    }

    /**
     * Add a panel to a group.
     *
     * @param string   $groupId  Group identifier
     * @param string   $id       Panel identifier
     * @param string   $label    Panel label
     * @param callable $callback Render callback receiving WP_Post
     * @param string   $context  Context: 'normal' or 'attachment'
     */
    public function addPanel(string $groupId, string $id, string $label, callable $callback, string $context = 'normal'): void
    {
        if (!isset($this->groups[$groupId])) {
            return;
        }

        $this->groups[$groupId]['items'][] = [
            'id' => $id,
            'label' => $label,
            'callback' => $callback,
            'context' => $context,
        ];
    }

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorLoader']);
        add_filter('admin_body_class', [$this, 'addAdminBodyClass']);
    }

    public function addMetaBox(): void
    {
        $postTypes = get_post_types(['public' => true]);

        $whiteLabel = get_option('sseo_ai_white_label', []);
        $companyName = !empty($whiteLabel['company_name']) ? $whiteLabel['company_name'] : 'Fyndable';
        $metaBoxTitle = $companyName . ' SEO';

        $hasNormal = false;
        $hasAttachment = false;

        foreach ($this->groups as $group) {
            foreach ($group['items'] as $item) {
                if ($item['context'] === 'normal') {
                    $hasNormal = true;
                } else {
                    $hasAttachment = true;
                }
            }
        }

        if ($hasNormal) {
            foreach ($postTypes as $postType) {
                add_meta_box(
                    'fyndable_seo_meta',
                    $metaBoxTitle,
                    [$this, 'renderMetaBox'],
                    $postType,
                    'normal',
                    'high'
                );
            }
        }

        if ($hasAttachment) {
            add_meta_box(
                'fyndable_seo_meta_attachment',
                $metaBoxTitle,
                [$this, 'renderAttachmentMetaBox'],
                'attachment',
                'normal',
                'high'
            );
        }
    }

    /**
     * Scope the meta-box theme tokens to the editor screens.
     *
     * The plugin admin pages carry `fyndable-ui` from Client::addAdminBodyClass(),
     * but post.php / post-new.php / attachment.php do not (client-admin.css is not
     * loaded there). This emits a dedicated scope so the editor panel can use the
     * very same design tokens without touching the rest of the edit screen.
     */
    public function addAdminBodyClass(string $classes): string
    {
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post') {
            return $classes;
        }

        $classes .= ' fyndable-seo-editor';

        // The editor always opens in the light theme; the header toggle switches
        // to dark for the current browser (remembered client-side). `?fyndable_dark=1`
        // forces dark for a single page load.
        if (isset($_GET['fyndable_dark']) && $_GET['fyndable_dark'] === '1') {
            $classes .= ' fyndable-dark';
        }

        return $classes;
    }

    public function enqueueAssets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php', 'attachment.php'], true)) {
            return;
        }

        wp_enqueue_style('dashicons');

        wp_enqueue_style(
            'fyndable-post-metabox',
            SSEO_AI_CLIENT_PLUGIN_URL . 'assets/post-metabox.css',
            [],
            SSEO_AI_CLIENT_VERSION . '.' . filemtime(SSEO_AI_CLIENT_PLUGIN_DIR . 'assets/post-metabox.css')
        );
        $whiteLabelCss = $this->getWhiteLabelCSS();
        if ($whiteLabelCss !== '') {
            wp_add_inline_style('fyndable-post-metabox', $whiteLabelCss);
        }

        wp_register_script('fyndable-post-metabox', false);
        wp_enqueue_script('fyndable-post-metabox');
        wp_add_inline_script('fyndable-post-metabox', $this->getInlineJS());

        // Global AI loader (animated Fyndable logo) — scoped to our metaboxes so
        // WordPress core spinners on the edit screen are left untouched.
        $this->enqueueFyndableLoader('#fyndable_seo_meta, #fyndable_seo_meta_attachment');
    }

    /**
     * Load the AI loader inside the block editor as well. When the editor runs
     * iframed, admin_enqueue_scripts lands in the parent document while the
     * plugin sidebar JS runs inside the iframe — this covers both.
     */
    public function enqueueEditorLoader(): void
    {
        $this->enqueueFyndableLoader('#fyndable_seo_meta, #fyndable_seo_meta_attachment');
    }

    private function enqueueFyndableLoader(string $scopeSelector): void
    {
        $whiteLabel = get_option('sseo_ai_white_label', []);
        $loaderColors = null;
        if (!empty($whiteLabel['company_name']) && (!empty($whiteLabel['primary_color']) || !empty($whiteLabel['secondary_color']))) {
            $loaderColors = [
                sanitize_hex_color($whiteLabel['primary_color'] ?? '') ?: '#379fd3',
                sanitize_hex_color($whiteLabel['secondary_color'] ?? '') ?: '#8f39ac',
            ];
        }
        wp_enqueue_script(
            'fyndable-loader',
            SSEO_AI_CLIENT_PLUGIN_URL . 'assets/fyndable-loader.js',
            ['jquery'],
            SSEO_AI_CLIENT_VERSION . '.' . filemtime(SSEO_AI_CLIENT_PLUGIN_DIR . 'assets/fyndable-loader.js'),
            true
        );
        wp_localize_script('fyndable-loader', 'fyndableLoaderConfig', [
            'text' => __('AI is generating', 'ai-seo-client'),
            'scopeSelector' => $scopeSelector,
            'colors' => $loaderColors,
        ]);
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $this->renderGroupedContent($post, 'normal');
    }

    public function renderAttachmentMetaBox(\WP_Post $post): void
    {
        $this->renderGroupedContent($post, 'attachment');
    }

    private function renderGroupedContent(\WP_Post $post, string $context): void
    {
        $groupsWithItems = [];
        foreach ($this->groups as $group) {
            $items = array_filter($group['items'], fn($i) => $i['context'] === $context);
            if (!empty($items)) {
                $groupsWithItems[] = [
                    'id' => $group['id'],
                    'label' => $group['label'],
                    'icon' => $group['icon'],
                    'accent' => $group['accent'],
                    'items' => array_values($items),
                ];
            }
        }

        if (empty($groupsWithItems)) {
            echo '<p>' . esc_html__('No SEO options available.', 'ai-seo-client') . '</p>';
            return;
        }

        $whiteLabel = get_option('sseo_ai_white_label', []);
        $companyName = !empty($whiteLabel['company_name']) ? $whiteLabel['company_name'] : 'Fyndable';
        $companyLogo = !empty($whiteLabel['company_logo']) ? $whiteLabel['company_logo'] : '';
        $brandName = $companyName . ' Smart SEO';

        echo '<div class="fyndable-seo-container">';

        // Brand header band
        echo '<div class="fyndable-seo-header">';
        echo '<span class="fyndable-seo-logo">';
        if ($companyLogo) {
            echo '<img src="' . esc_url($companyLogo) . '" alt="' . esc_attr($brandName) . '">';
        } elseif (!empty($whiteLabel['company_name'])) {
            echo esc_html($brandName);
        } else {
            echo '<img src="' . esc_url(SSEO_AI_CLIENT_PLUGIN_URL . 'assets/FYN-LOGO-DIA-LONG300ppi.png')
                . '" alt="' . esc_attr($brandName) . '">';
        }
        echo '</span>';
        echo '<span class="fyndable-seo-header-spacer"></span>';
        echo '<button type="button" class="fyndable-seo-themetoggle" title="'
            . esc_attr__('Switch theme', 'ai-seo-client') . '">';
        echo '<span class="fyn-sun" aria-hidden="true">&#9728;</span>';
        echo '<span class="fyn-moon" aria-hidden="true">&#9790;</span>';
        echo '</button>';
        echo '<span class="fyndable-seo-badge">' . esc_html__('Post Optimization', 'ai-seo-client') . '</span>';
        echo '</div>';

        // Fact check badge (if a fact check has been run for this post)
        $factCheckMeta = get_post_meta($post->ID, '_sseo_ai_fact_check', true);
        if (is_array($factCheckMeta)) {
            $this->renderFactCheckBadge($factCheckMeta);
        }

        // Accordion groups
        foreach ($groupsWithItems as $gIndex => $group) {
            $isOpen = $gIndex === 0;
            $groupState = $isOpen ? ' open' : '';
            $accent = sanitize_hex_color($group['accent']) ?: '';
            $accentStyle = $accent ? ' style="--fyn-sect-accent:' . esc_attr($accent) . '"' : '';

            echo '<div class="fyndable-seo-group' . $groupState . '"' . $accentStyle . '>';
            echo '<button type="button" class="fyndable-seo-group-header" data-group="' . esc_attr($group['id'])
                . '" aria-expanded="' . ($isOpen ? 'true' : 'false') . '">';
            if (strpos($group['icon'], 'dashicons-') === 0) {
                echo '<span class="fyndable-seo-group-icon dashicons ' . esc_attr($group['icon']) . '"></span>';
            } elseif ($group['icon'] !== '') {
                echo '<span class="fyndable-seo-group-icon">' . $group['icon'] . '</span>';
            }
            echo '<span class="fyndable-seo-group-title">' . esc_html($group['label']) . '</span>';
            echo '<span class="fyndable-seo-group-count">' . count($group['items']) . '</span>';
            echo '<span class="fyndable-seo-chevron dashicons dashicons-arrow-down-alt2"></span>';
            echo '</button>';

            $panelDisplay = $isOpen ? '' : ' style="display:none;"';
            echo '<div class="fyndable-seo-group-body"' . $panelDisplay . '>';

            // Sub-tabs within group
            if (count($group['items']) > 1) {
                echo '<div class="fyndable-seo-subtabs" data-group="' . esc_attr($group['id']) . '">';
                echo '<ul class="fyndable-seo-subtab-nav">';
                foreach ($group['items'] as $iIndex => $item) {
                    $active = $iIndex === 0 ? ' class="active"' : '';
                    printf(
                        '<li%s><a href="#" data-subtab="%s-%s">%s</a></li>',
                        $active,
                        esc_attr($group['id']),
                        esc_attr($item['id']),
                        esc_html($item['label'])
                    );
                }
                echo '</ul>';

                foreach ($group['items'] as $iIndex => $item) {
                    $panelId = $group['id'] . '-' . $item['id'];
                    $display = $iIndex === 0 ? '' : ' style="display:none;"';
                    printf('<div class="fyndable-seo-subtab-panel" id="%s"%s>', esc_attr($panelId), $display);
                    call_user_func($item['callback'], $post);
                    echo '</div>';
                }

                echo '</div>';
            } else {
                // Single item — no sub-tabs needed
                echo '<div class="fyndable-seo-group-fields">';
                call_user_func($group['items'][0]['callback'], $post);
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Render a fact-check warning badge from stored post meta.
     */
    private function renderFactCheckBadge(array $report): void
    {
        $verdict = $report['verdict'] ?? 'uncertain';
        $warnings = $report['warnings'] ?? [];
        $verdictAccents = [
            'supported' => 'var(--fyn-success)',
            'uncertain' => 'var(--fyn-warning)',
            'questionable' => 'var(--fyn-danger)',
        ];
        $verdictText = [
            'supported' => 'var(--fyn-success-text)',
            'uncertain' => 'var(--fyn-warning-text)',
            'questionable' => 'var(--fyn-danger-text)',
        ];
        $verdictIcons = [
            'supported' => 'dashicons-yes-alt',
            'uncertain' => 'dashicons-warning',
            'questionable' => 'dashicons-dismiss',
        ];
        $verdictLabels = [
            'supported' => __('Fact check: supported', 'ai-seo-client'),
            'uncertain' => __('Fact check: uncertain', 'ai-seo-client'),
            'questionable' => __('Fact check: questionable', 'ai-seo-client'),
        ];
        $accent = $verdictAccents[$verdict] ?? 'var(--text-m)';
        $textColor = $verdictText[$verdict] ?? 'var(--text-b)';
        $icon = $verdictIcons[$verdict] ?? 'dashicons-info';
        $label = $verdictLabels[$verdict] ?? __('Fact check: unknown', 'ai-seo-client');
        $warningCount = count($warnings);

        echo '<div class="sseo-fact-check-badge" style="--fyn-fact-accent:' . esc_attr($accent) . '">';
        echo '<div style="display:flex;align-items:center;gap:9px;">';
        echo '<span class="dashicons ' . esc_attr($icon)
            . '" style="width:17px;height:17px;font-size:17px;line-height:17px;color:' . esc_attr($accent) . ';"></span>';
        echo '<span style="font-size:13.5px;font-weight:700;color:' . esc_attr($textColor) . ';">'
            . esc_html($label) . '</span>';
        if ($warningCount > 0) {
            echo '<span class="fyn-badge" style="background:' . esc_attr($accent) . ';color:#fff;">'
                . esc_html((string) $warningCount) . '</span>';
        }
        echo '</div>';

        if (!empty($warnings)) {
            echo '<ul>';
            foreach ($warnings as $warning) {
                echo '<li>' . esc_html($warning) . '</li>';
            }
            echo '</ul>';
        }

        echo '<p>'
            . esc_html__('This is an AI self-assessment, not external fact verification.', 'ai-seo-client')
            . '</p>';
        echo '</div>';
    }

    /**
     * White-label accent overrides. Returns an empty string when no custom brand
     * is configured — assets/post-metabox.css already carries the Fyndable values.
     */
    private function getWhiteLabelCSS(): string
    {
        $whiteLabel = get_option('sseo_ai_white_label', []);
        if (empty($whiteLabel['company_name'])) {
            return '';
        }

        $primaryColor = sanitize_hex_color($whiteLabel['primary_color'] ?? '') ?: '#379fd3';
        $secondaryColor = sanitize_hex_color($whiteLabel['secondary_color'] ?? '') ?: '#8f39ac';
        $usePrimaryOnly = !empty($whiteLabel['use_primary_only']);
        $gradient = $usePrimaryOnly
            ? $primaryColor
            : "linear-gradient(135deg, {$primaryColor} 0%, {$secondaryColor} 100%)";

        // Accents only — surfaces, text and wells keep using the shared tokens.
        return "body.fyndable-seo-editor {"
            . " --fyn-purple: {$secondaryColor};"
            . " --fyn-blue: {$primaryColor};"
            . " --fyn-gradient: {$gradient};"
            . " --fyn-gradient-flat: linear-gradient(90deg, {$primaryColor}, {$secondaryColor});"
            . " --fyn-purple-tint: {$secondaryColor}1f;"
            . " --fyn-blue-tint: {$primaryColor}1f;"
            . " --input-focus: {$secondaryColor};"
            . " }\n"
            . ".fyndable-seo-header { background: {$gradient}; }\n";
    }

    private function getInlineJS(): string
    {
        return <<<'JS'
(function() {
    document.addEventListener('DOMContentLoaded', function() {

        // Global AI loader overlay is provided by assets/fyndable-loader.js
        // (enqueued above); sseoShowLoader/sseoHideLoader come from there.

        // Accordion group toggle (exclusive — opening one closes others)
        var container = document.querySelector('.fyndable-seo-container');
        var groupHeaders = document.querySelectorAll('.fyndable-seo-group-header');
        groupHeaders.forEach(function(header) {
            header.addEventListener('click', function(e) {
                e.preventDefault();
                var group = header.closest('.fyndable-seo-group');
                if (!group) return;
                var body = group.querySelector('.fyndable-seo-group-body');
                var isOpen = group.classList.contains('open');

                // Close all groups
                if (container) {
                    container.querySelectorAll('.fyndable-seo-group').forEach(function(g) {
                        g.classList.remove('open');
                        var b = g.querySelector('.fyndable-seo-group-body');
                        if (b) b.style.display = 'none';
                        var h = g.querySelector('.fyndable-seo-group-header');
                        if (h) h.setAttribute('aria-expanded', 'false');
                    });
                }

                // Open clicked group if it was closed
                if (!isOpen) {
                    group.classList.add('open');
                    if (body) body.style.display = '';
                    header.setAttribute('aria-expanded', 'true');
                }
            });
        });

        // Sub-tab switching
        var subtabLinks = document.querySelectorAll('.fyndable-seo-subtab-nav a');
        subtabLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var subtabId = link.getAttribute('data-subtab');
                var subtabContainer = link.closest('.fyndable-seo-subtabs');
                if (!subtabContainer) return false;

                subtabContainer.querySelectorAll('.fyndable-seo-subtab-nav li').forEach(function(el) {
                    el.classList.remove('active');
                });
                link.closest('li').classList.add('active');

                subtabContainer.querySelectorAll('.fyndable-seo-subtab-panel').forEach(function(panel) {
                    panel.style.display = panel.id === subtabId ? '' : 'none';
                });
                return false;
            });
        });

        // Theme toggle in the brand header band. The panel opens light; the
        // chosen theme is remembered in this browser only, so other admin
        // screens keep their own setting.
        var THEME_KEY = 'fyndableSeoEditorTheme';
        var storedTheme = null;
        try { storedTheme = window.localStorage.getItem(THEME_KEY); } catch (err) {}
        if (storedTheme === 'dark') {
            document.body.classList.add('fyndable-dark');
        }

        var themeToggle = document.querySelector('.fyndable-seo-themetoggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', function(e) {
                e.preventDefault();
                var dark = !document.body.classList.contains('fyndable-dark');
                document.body.classList.toggle('fyndable-dark', dark);
                try { window.localStorage.setItem(THEME_KEY, dark ? 'dark' : 'light'); } catch (err) {}
            });
        }
    });
})();
JS;
    }
}

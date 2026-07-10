<?php

namespace SSEOAIClient;

/**
 * Unified Post Meta Box with Grouped Accordion
 * 
 * Replaces 20+ individual meta boxes on the post edit screen
 * with a single "Fyndable SEO" meta box containing grouped
 * accordion sections with Fyndable branding.
 */
class PostMetaBox
{
    private array $groups = [];
    private array $items = [];

    /**
     * Define a group (accordion section).
     */
    public function addGroup(string $id, string $label, string $icon = ''): void
    {
        $this->groups[$id] = [
            'id' => $id,
            'label' => $label,
            'icon' => $icon,
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
    }

    public function addMetaBox(): void
    {
        $postTypes = get_post_types(['public' => true]);

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
                    __('Fyndable SEO', 'ai-seo-client'),
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
                __('Fyndable SEO', 'ai-seo-client'),
                [$this, 'renderAttachmentMetaBox'],
                'attachment',
                'normal',
                'high'
            );
        }
    }

    public function enqueueAssets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php', 'attachment.php'], true)) {
            return;
        }

        wp_register_style('fyndable-post-metabox', false);
        wp_enqueue_style('fyndable-post-metabox');
        wp_add_inline_style('fyndable-post-metabox', $this->getInlineCSS());

        wp_register_script('fyndable-post-metabox', false);
        wp_enqueue_script('fyndable-post-metabox');
        wp_localize_script('fyndable-post-metabox', 'sseoAiLoaderText', [
            'text' => __('AI is generating... Please wait.', 'ai-seo-client'),
        ]);
        wp_add_inline_script('fyndable-post-metabox', $this->getInlineJS());
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
                    'items' => $items,
                ];
            }
        }

        if (empty($groupsWithItems)) {
            echo '<p>' . esc_html__('No SEO options available.', 'ai-seo-client') . '</p>';
            return;
        }

        echo '<div class="fyndable-seo-container">';

        // Gradient header
        echo '<div class="fyndable-seo-header">';
        echo '<span class="fyndable-seo-logo">Fyndable <strong>SmartSEO</strong></span>';
        echo '<span class="fyndable-seo-badge">' . esc_html__('Post Optimization', 'ai-seo-client') . '</span>';
        echo '</div>';

        // Accordion groups
        foreach ($groupsWithItems as $gIndex => $group) {
            $isOpen = $gIndex === 0;
            $groupState = $isOpen ? ' open' : '';

            echo '<div class="fyndable-seo-group' . $groupState . '">';
            echo '<button type="button" class="fyndable-seo-group-header" data-group="' . esc_attr($group['id']) . '">';
            echo '<span class="fyndable-seo-group-icon">' . $group['icon'] . '</span>';
            echo '<span class="fyndable-seo-group-title">' . esc_html($group['label']) . '</span>';
            echo '<span class="fyndable-seo-group-count">' . count($group['items']) . '</span>';
            echo '<span class="fyndable-seo-chevron">&#9662;</span>';
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
                call_user_func($group['items'][0]['callback'], $post);
            }

            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    private function getInlineCSS(): string
    {
        return <<<'CSS'
/* Container */
.fyndable-seo-container { margin: -6px -12px -12px; }

/* Gradient header */
.fyndable-seo-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px;
    background: linear-gradient(135deg, #3b82f6 0%, #ec4899 50%, #FF4D00 100%);
    color: #fff;
}
.fyndable-seo-logo { font-size: 15px; letter-spacing: 0.3px; opacity: 0.95; }
.fyndable-seo-logo strong { font-weight: 700; }
.fyndable-seo-badge {
    font-size: 11px; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.5px; padding: 3px 10px; border-radius: 20px;
    background: rgba(255,255,255,0.2); color: #fff;
}

/* Accordion groups */
.fyndable-seo-group { border-bottom: 1px solid #e0e0e0; }
.fyndable-seo-group:last-child { border-bottom: none; }

.fyndable-seo-group-header {
    display: flex; align-items: center; width: 100%;
    padding: 12px 18px; border: none; background: #f8f9fb;
    cursor: pointer; font-size: 13px; font-weight: 600;
    color: #1e1e1e; text-align: left; transition: background 0.15s;
}
.fyndable-seo-group-header:hover { background: #eef2ff; }
.fyndable-seo-group-icon { margin-right: 8px; font-size: 16px; line-height: 1; }
.fyndable-seo-group-title { flex: 1; }
.fyndable-seo-group-count {
    font-size: 11px; font-weight: 600; color: #6b7280;
    background: #e5e7eb; border-radius: 10px; padding: 1px 8px; margin-right: 8px;
}
.fyndable-seo-chevron { font-size: 12px; color: #9ca3af; transition: transform 0.2s; }
.fyndable-seo-group.open .fyndable-seo-chevron { transform: rotate(180deg); }
.fyndable-seo-group.open .fyndable-seo-group-header {
    background: #eef2ff; color: #1e40af;
    border-left: 3px solid #3b82f6; padding-left: 15px;
}

/* Group body */
.fyndable-seo-group-body { padding: 0; background: #fff; }

/* Sub-tabs */
.fyndable-seo-subtabs { }
.fyndable-seo-subtab-nav {
    display: flex; flex-wrap: wrap; margin: 0; padding: 0;
    background: #f3f4f6; border-bottom: 1px solid #e0e0e0;
}
.fyndable-seo-subtab-nav li { list-style: none; margin: 0; }
.fyndable-seo-subtab-nav li a {
    display: block; padding: 8px 14px; text-decoration: none;
    color: #6b7280; font-size: 12px; font-weight: 500;
    border-bottom: 2px solid transparent; margin-bottom: -1px;
    transition: all 0.15s;
}
.fyndable-seo-subtab-nav li a:hover { color: #1e40af; background: #fff; }
.fyndable-seo-subtab-nav li.active a {
    color: #1e40af; border-bottom-color: #3b82f6; background: #fff;
    font-weight: 600;
}
.fyndable-seo-subtab-panel { padding: 16px 18px; }
.fyndable-seo-subtab-panel h3,
.fyndable-seo-subtab-panel h4 { margin-top: 0; }
.fyndable-seo-subtab-panel table.form-table { margin-top: 10px; }
.fyndable-seo-subtab-panel .inside { margin: 0; padding: 0; }

/* WordPress meta box adjustments */
#fyndable_seo_meta .postbox-header,
#fyndable_seo_meta_attachment .postbox-header { display: none; }
#fyndable_seo_meta .inside,
#fyndable_seo_meta_attachment .inside { padding: 0; margin: 0; }

/* Global AI loader overlay */
#sseo-ai-loader-overlay {
    display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.6); z-index: 99999; justify-content: center; align-items: center;
    flex-direction: column; backdrop-filter: blur(4px);
}
#sseo-ai-loader-overlay.active { display: flex !important; }
#sseo-ai-loader-overlay .sseo-loader-spinner {
    width: 60px; height: 60px; border: 5px solid rgba(255,255,255,0.3);
    border-top-color: #fff; border-radius: 50%; animation: sseo-spin 1s linear infinite;
}
#sseo-ai-loader-overlay .sseo-loader-text {
    color: #fff; margin-top: 20px; font-size: 16px; font-weight: 500;
    text-align: center; max-width: 300px; line-height: 1.5;
}
@keyframes sseo-spin { to { transform: rotate(360deg); } }
CSS;
    }

    private function getInlineJS(): string
    {
        return <<<'JS'
(function() {
    document.addEventListener('DOMContentLoaded', function() {

        // Inject global AI loader overlay if not present
        if (!document.getElementById('sseo-ai-loader-overlay')) {
            var overlay = document.createElement('div');
            overlay.id = 'sseo-ai-loader-overlay';
            overlay.innerHTML = '<div class="sseo-loader-spinner"></div><div class="sseo-loader-text">' + (window.sseoAiLoaderText ? window.sseoAiLoaderText.text : 'AI is generating...') + '</div>';
            document.body.appendChild(overlay);
        }
        window.sseoShowLoader = function() { jQuery('#sseo-ai-loader-overlay').addClass('active'); };
        window.sseoHideLoader = function() { jQuery('#sseo-ai-loader-overlay').removeClass('active'); };

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
                    });
                }

                // Open clicked group if it was closed
                if (!isOpen) {
                    group.classList.add('open');
                    if (body) body.style.display = '';
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
    });
})();
JS;
    }
}


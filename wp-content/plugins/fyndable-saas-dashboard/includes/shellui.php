<?php

namespace SSEOAISaaS;

/**
 * Shell UI helpers
 *
 * Small presentation-only builders for the Fyndable shell components, so
 * every screen renders the same page header / stat tile markup instead of
 * repeating it. Pure markup: no data access, no side effects.
 */
class ShellUi
{
    /**
     * Open a screen: the .fyn-main wrapper plus the page header.
     *
     * @param string $root    Portal label shown first in the breadcrumb.
     * @param string $crumb   Current screen, shown in brand purple.
     * @param string $title   H1 text.
     * @param string $desc    One-line description under the H1.
     * @param array  $actions Buttons, right-aligned. Each entry:
     *                        ['label' => string, 'url' => string,
     *                         'icon' => dashicon class, 'primary' => bool].
     */
    public static function openScreen(
        string $root,
        string $crumb,
        string $title,
        string $desc = '',
        array $actions = []
    ): void {
        echo '<div class="fyn-main"><div class="fyn-main-inner fyn-animate-in">';
        echo '<div class="fyn-page-header"><div class="fyn-page-header-left">';

        echo '<div class="fyn-breadcrumb">' . esc_html($root)
            . ' <span class="dashicons dashicons-arrow-right-alt2" style="font-size:12px;width:12px;height:12px;vertical-align:middle"></span> '
            . '<span class="fyn-crumb">' . esc_html($crumb) . '</span></div>';

        echo '<h1>' . esc_html($title) . '</h1>';

        if ($desc !== '') {
            echo '<p class="fyn-page-desc">' . esc_html($desc) . '</p>';
        }

        echo '</div>';

        if (!empty($actions)) {
            echo '<div class="fyn-page-header-actions">';
            foreach ($actions as $action) {
                $class = !empty($action['primary']) ? 'fyn-btn fyn-btn-primary' : 'fyn-btn fyn-btn-outline';
                echo '<a href="' . esc_url($action['url'] ?? '#') . '" class="' . esc_attr($class) . '">';
                if (!empty($action['icon'])) {
                    echo '<span class="dashicons ' . esc_attr($action['icon']) . '"></span>';
                }
                echo esc_html($action['label'] ?? '') . '</a>';
            }
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Close a screen opened with openScreen().
     */
    public static function closeScreen(): void
    {
        echo '</div></div>';
    }

    /**
     * Emit only the page header, with no wrapper element.
     *
     * Use this in place of a screen's bare <h1> when the screen keeps its
     * existing container: the legacy bridge already gives .wrap the shell's
     * padding and centred column, so no .fyn-main is needed and there is
     * nothing to close.
     *
     * @see openScreen() for the parameter meanings.
     */
    public static function pageHeader(
        string $root,
        string $crumb,
        string $title,
        string $desc = '',
        array $actions = []
    ): void {
        echo '<div class="fyn-page-header fyn-animate-in"><div class="fyn-page-header-left">';

        echo '<div class="fyn-breadcrumb">' . esc_html($root)
            . ' <span class="dashicons dashicons-arrow-right-alt2" style="font-size:12px;width:12px;height:12px;vertical-align:middle"></span> '
            . '<span class="fyn-crumb">' . esc_html($crumb) . '</span></div>';

        echo '<h1>' . esc_html($title) . '</h1>';

        if ($desc !== '') {
            echo '<p class="fyn-page-desc">' . esc_html($desc) . '</p>';
        }

        echo '</div>';

        if (!empty($actions)) {
            echo '<div class="fyn-page-header-actions">';
            foreach ($actions as $action) {
                $class = !empty($action['primary']) ? 'fyn-btn fyn-btn-primary' : 'fyn-btn fyn-btn-outline';
                echo '<a href="' . esc_url($action['url'] ?? '#') . '" class="' . esc_attr($class) . '">';
                if (!empty($action['icon'])) {
                    echo '<span class="dashicons ' . esc_attr($action['icon']) . '"></span>';
                }
                echo esc_html($action['label'] ?? '') . '</a>';
            }
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Render a 4-up row of stat tiles.
     *
     * @param array $tiles Each entry: ['label' => string, 'value' => string,
     *                     'icon' => dashicon class, 'sub' => string,
     *                     'delta' => string, 'delta_type' => success|warning|danger|info|accent].
     */
    public static function statTiles(array $tiles): void
    {
        if (empty($tiles)) {
            return;
        }

        echo '<div class="fyn-stats-grid">';
        foreach ($tiles as $tile) {
            echo '<div class="fyn-stat-tile">';
            echo '<div class="fyn-stat-tile-top">';
            echo '<span class="fyn-stat-tile-label">' . esc_html($tile['label'] ?? '') . '</span>';
            if (!empty($tile['icon'])) {
                echo '<span class="dashicons ' . esc_attr($tile['icon']) . '"></span>';
            }
            echo '</div>';
            echo '<div class="fyn-stat-tile-value">' . esc_html((string)($tile['value'] ?? '')) . '</div>';
            echo '<div class="fyn-stat-tile-footer">';
            if (!empty($tile['delta'])) {
                $type = $tile['delta_type'] ?? 'success';
                echo '<span class="fyn-delta fyn-delta--' . esc_attr($type) . '">' . esc_html($tile['delta']) . '</span>';
            }
            if (!empty($tile['sub'])) {
                echo esc_html($tile['sub']);
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Tier badge modifier for a tier slug.
     */
    public static function tierClass(string $tier): string
    {
        $known = ['trial', 'starter', 'professional', 'business', 'agency', 'free', 'lifetime'];
        $tier = strtolower($tier);

        return in_array($tier, $known, true) ? 'fyn-tier--' . $tier : 'fyn-tier--free';
    }

    /**
     * Progress-bar modifier, colour-graded by percentage used.
     */
    public static function barClass(int $percent): string
    {
        if ($percent >= 90) {
            return 'fyn-progress-bar--danger';
        }
        if ($percent >= 70) {
            return 'fyn-progress-bar--warning';
        }

        return '';
    }
}

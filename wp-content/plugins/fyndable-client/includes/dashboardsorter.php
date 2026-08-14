<?php

namespace SSEOAIClient;

/**
 * Dashboard Sorter
 *
 * Adds drag-and-drop ordering for dashboard cards on selected admin pages.
 * The saved order is stored per page in wp_options and restored on page load.
 */
class DashboardSorter
{
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRestRoutes']);
    }

    public static function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/dashboard/order', [
            'methods' => 'POST',
            'callback' => [self::class, 'restSaveOrder'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public static function restSaveOrder(\WP_REST_Request $request): \WP_REST_Response
    {
        $page = sanitize_key($request->get_param('page') ?? '');
        $order = $request->get_param('order');

        if (!$page || !is_array($order)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Invalid request'], 400);
        }

        $sanitized = array_map('sanitize_text_field', $order);
        update_option('sseo_dashboard_card_order_' . $page, $sanitized);

        return new \WP_REST_Response(['success' => true, 'page' => $page], 200);
    }

    public static function getOrder(string $page): array
    {
        $saved = get_option('sseo_dashboard_card_order_' . $page, []);
        return is_array($saved) ? $saved : [];
    }

    /**
     * Open the sortable container. Wrap all .postbox/.sseo-ai-dashboard-card
     * elements between begin() and end() calls.
     */
    public static function begin(string $page): void
    {
        echo '<div id="sseo-sortable-cards" data-page="' . esc_attr($page) . '">';
    }

    /**
     * Close the sortable container and print the required inline JavaScript.
     */
    public static function end(string $page): void
    {
        $order = self::getOrder($page);
        echo '</div>';
        ?>
        <style>
            #sseo-sortable-cards { position: relative; }
            .sseo-reorder-toggle {
                position: absolute;
                top: -44px;
                left: 0;
                z-index: 5;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #fff;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                padding: 6px 12px;
                font-size: 13px;
                font-weight: 600;
                color: #374151;
                cursor: pointer;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                transition: background .15s, border-color .15s;
            }
            .sseo-reorder-toggle:hover { border-color: #379fd3; color: #379fd3; }
            .sseo-reorder-toggle.active {
                background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%);
                color: #fff;
                border-color: transparent;
            }
            .sseo-reorder-toggle svg { width: 16px; height: 16px; }

            /* Reorder mode: show number badge + up/down controls on each card */
            #sseo-sortable-cards.sseo-reorder-mode > [data-card-id] { position: relative; }
            .sseo-card-order-badge {
                display: none;
                position: absolute;
                top: 10px;
                left: 10px;
                z-index: 6;
                min-width: 26px;
                height: 26px;
                padding: 0 8px;
                border-radius: 13px;
                background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%);
                color: #fff;
                font-size: 13px;
                font-weight: 700;
                line-height: 26px;
                text-align: center;
                box-shadow: 0 2px 6px rgba(55,159,211,0.35);
            }
            .sseo-card-controls {
                display: none;
                position: absolute;
                top: 10px;
                right: 10px;
                z-index: 6;
                gap: 6px;
            }
            .sseo-card-controls button {
                width: 30px;
                height: 30px;
                border-radius: 6px;
                border: 1px solid #d1d5db;
                background: #fff;
                color: #374151;
                font-size: 16px;
                font-weight: 700;
                line-height: 1;
                cursor: pointer;
                padding: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .sseo-card-controls button:hover { border-color: #379fd3; color: #379fd3; }
            .sseo-card-controls button:disabled { opacity: .4; cursor: not-allowed; }
            #sseo-sortable-cards.sseo-reorder-mode .sseo-card-order-badge { display: block; }
            #sseo-sortable-cards.sseo-reorder-mode .sseo-card-controls { display: inline-flex; }
            #sseo-sortable-cards.sseo-reorder-mode > [data-card-id] {
                outline: 2px dashed rgba(55,159,211,0.4);
                outline-offset: -2px;
            }
        </style>
        <button type="button" class="sseo-reorder-toggle" id="sseo-reorder-toggle" aria-pressed="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            <span class="sseo-reorder-label"><?php echo esc_js(__('Herschikken', 'ai-seo-client')); ?></span>
        </button>
        <script>
        (function() {
            var container = document.getElementById('sseo-sortable-cards');
            if (!container) return;

            var page = container.getAttribute('data-page');
            var initialOrder = <?php echo json_encode(array_values($order)); ?>;
            var nonce = <?php echo json_encode(wp_create_nonce('wp_rest')); ?>;
            var restUrl = <?php echo json_encode(esc_url_raw(rest_url('sseo-ai/v1/dashboard/order'))); ?>;
            var reorderMode = false;

            function assignCardIds() {
                Array.from(container.children).forEach(function(child, index) {
                    if (!child.getAttribute('data-card-id')) {
                        child.setAttribute('data-card-id', 'sseo-card-' + index);
                    }
                });
            }

            function restoreOrder() {
                if (!initialOrder.length) return;
                var children = Array.from(container.children);
                var fragment = document.createDocumentFragment();
                initialOrder.forEach(function(id) {
                    var match = children.find(function(el) { return el.getAttribute('data-card-id') === id; });
                    if (match) {
                        fragment.appendChild(match);
                    }
                });
                children.forEach(function(el) {
                    if (!fragment.contains(el)) {
                        fragment.appendChild(el);
                    }
                });
                container.appendChild(fragment);
            }

            function clearCardChrome() {
                container.querySelectorAll('.sseo-card-order-badge, .sseo-card-controls').forEach(function(el) { el.remove(); });
            }

            function buildCardChrome() {
                clearCardChrome();
                var cards = Array.from(container.children).filter(function(el) { return el.getAttribute('data-card-id'); });
                cards.forEach(function(card, index) {
                    var badge = document.createElement('div');
                    badge.className = 'sseo-card-order-badge';
                    badge.textContent = String(index + 1);
                    card.appendChild(badge);

                    var controls = document.createElement('div');
                    controls.className = 'sseo-card-controls';

                    var up = document.createElement('button');
                    up.type = 'button';
                    up.innerHTML = '&uarr;';
                    up.title = <?php echo json_encode(__('Omhoog', 'ai-seo-client')); ?>;
                    up.disabled = index === 0;
                    up.addEventListener('click', function(e) {
                        e.preventDefault();
                        moveCard(card, -1);
                    });

                    var down = document.createElement('button');
                    down.type = 'button';
                    down.innerHTML = '&darr;';
                    down.title = <?php echo json_encode(__('Omlaag', 'ai-seo-client')); ?>;
                    down.disabled = index === cards.length - 1;
                    down.addEventListener('click', function(e) {
                        e.preventDefault();
                        moveCard(card, 1);
                    });

                    controls.appendChild(up);
                    controls.appendChild(down);
                    card.appendChild(controls);
                });
            }

            function moveCard(card, direction) {
                var cards = Array.from(container.children).filter(function(el) { return el.getAttribute('data-card-id'); });
                var index = cards.indexOf(card);
                var newIndex = index + direction;
                if (newIndex < 0 || newIndex >= cards.length) return;
                if (direction < 0) {
                    container.insertBefore(card, cards[newIndex]);
                } else {
                    container.insertBefore(card, cards[newIndex].nextSibling);
                }
                buildCardChrome();
                saveOrder();
            }

            function saveOrder() {
                var order = Array.from(container.children).map(function(el) {
                    return el.getAttribute('data-card-id');
                }).filter(Boolean);

                fetch(restUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ page: page, order: order }),
                }).catch(function(err) {
                    console.error('Failed to save dashboard order', err);
                });
            }

            function setReorderMode(on) {
                reorderMode = on;
                container.classList.toggle('sseo-reorder-mode', on);
                var toggle = document.getElementById('sseo-reorder-toggle');
                if (toggle) {
                    toggle.classList.toggle('active', on);
                    toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
                    var label = toggle.querySelector('.sseo-reorder-label');
                    if (label) {
                        label.textContent = on
                            ? <?php echo json_encode(__('Klaar', 'ai-seo-client')); ?>
                            : <?php echo json_encode(__('Herschikken', 'ai-seo-client')); ?>;
                    }
                }
                if (on) { buildCardChrome(); } else { clearCardChrome(); }
            }

            var toggleBtn = document.getElementById('sseo-reorder-toggle');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() { setReorderMode(!reorderMode); });
            }

            assignCardIds();
            restoreOrder();
        })();
        </script>
        <?php
    }
}

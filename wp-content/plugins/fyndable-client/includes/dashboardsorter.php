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
        <script>
        (function() {
            var container = document.getElementById('sseo-sortable-cards');
            if (!container) return;

            var page = container.getAttribute('data-page');
            var initialOrder = <?php echo json_encode(array_values($order)); ?>;
            var nonce = <?php echo json_encode(wp_create_nonce('wp_rest')); ?>;

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

            function makeDraggable(el) {
                el.setAttribute('draggable', 'true');
                el.style.cursor = 'grab';
                el.addEventListener('dragstart', function(e) {
                    var nestedDraggable = e.target.closest ? e.target.closest('[draggable="true"]') : null;
                    if (nestedDraggable && nestedDraggable !== el) {
                        return;
                    }
                    e.dataTransfer.setData('text/plain', el.getAttribute('data-card-id'));
                    el.classList.add('sseo-dragging');
                });
                el.addEventListener('dragend', function() {
                    el.classList.remove('sseo-dragging');
                    document.querySelectorAll('.sseo-drag-over').forEach(function(node) {
                        node.classList.remove('sseo-drag-over');
                    });
                });
                el.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    var after = getDragAfterElement(container, e.clientY);
                    var dragging = document.querySelector('.sseo-dragging');
                    if (!dragging) return;
                    if (after) {
                        container.insertBefore(dragging, after);
                    } else {
                        container.appendChild(dragging);
                    }
                });
            }

            function getDragAfterElement(container, y) {
                var draggableElements = Array.from(container.querySelectorAll('[data-card-id]:not(.sseo-dragging)'));
                return draggableElements.reduce(function(closest, child) {
                    var box = child.getBoundingClientRect();
                    var offset = y - box.top - box.height / 2;
                    if (offset < 0 && offset > closest.offset) {
                        return { offset: offset, element: child };
                    }
                    return closest;
                }, { offset: Number.NEGATIVE_INFINITY }).element;
            }

            function saveOrder() {
                var order = Array.from(container.children).map(function(el) {
                    return el.getAttribute('data-card-id');
                }).filter(Boolean);

                var restUrl = <?php echo json_encode(esc_url_raw(rest_url('sseo-ai/v1/dashboard/order'))); ?>;
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

            container.addEventListener('drop', function(e) {
                e.preventDefault();
                saveOrder();
            });

            assignCardIds();

            Array.from(container.children).forEach(function(child) {
                makeDraggable(child);
            });

            restoreOrder();
        })();
        </script>
        <?php
    }
}

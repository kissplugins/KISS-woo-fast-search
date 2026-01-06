<?php
/**
 * Floating Toolbar for KISS Customer & Order Search
 *
 * Adds a floating search bar to WooCommerce admin pages for quick customer lookup.
 *
 * @package KISS_Woo_Customer_Order_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_COS_Floating_Toolbar {

    /**
     * Singleton instance.
     *
     * @var KISS_Woo_COS_Floating_Toolbar|null
     */
    protected static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return KISS_Woo_COS_Floating_Toolbar
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_action( 'admin_footer', array( $this, 'render_toolbar' ) );
        add_action( 'admin_head', array( $this, 'output_css' ) );
        add_action( 'admin_footer', array( $this, 'output_js' ), 20 );
    }

    /**
     * Check if we should show the toolbar on this page.
     *
     * @return bool
     */
    protected function should_show_toolbar() {
        // Only show for users with WooCommerce access
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        // Show on all admin pages for now (CS reps work across many pages)
        return is_admin();
    }

    /**
     * Output CSS styles.
     */
    public function output_css() {
        if ( ! $this->should_show_toolbar() ) {
            return;
        }
        ?>
        <style>
            .kiss-floating-toolbar {
                position: fixed;
                top: 50px;
                right: 20px;
                z-index: 9998;
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
                padding: 12px 16px;
                min-width: 360px;
                max-width: 500px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            }
            .kiss-floating-toolbar.is-dragging {
                cursor: grabbing;
                user-select: none;
            }
            .kiss-floating-toolbar .kiss-drag-handle {
                cursor: grab;
                padding: 4px 0;
                margin-bottom: 8px;
                border-bottom: 1px solid #e0e0e0;
                text-align: center;
                color: #999;
                font-size: 11px;
            }
            .kiss-floating-toolbar .kiss-toolbar-label {
                font-size: 12px;
                font-weight: 600;
                color: #1d2327;
                margin-bottom: 8px;
            }
            .kiss-floating-toolbar .kiss-toolbar-label .kiss-speed-badge {
                background: #00a32a;
                color: #fff;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 10px;
                font-weight: 500;
                margin-left: 6px;
            }
            .kiss-floating-toolbar .kiss-search-row {
                display: flex;
                gap: 8px;
                align-items: center;
            }
            .kiss-floating-toolbar .kiss-search-input {
                flex: 1;
                padding: 8px 12px;
                border: 1px solid #8c8f94;
                border-radius: 3px;
                font-size: 13px;
            }
            .kiss-floating-toolbar .kiss-search-input:focus {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
                outline: none;
            }
            .kiss-floating-toolbar .kiss-search-submit {
                white-space: nowrap;
                padding: 8px 16px;
            }
            .kiss-floating-toolbar .kiss-search-status {
                font-size: 12px;
                color: #646970;
                font-style: italic;
                margin-top: 6px;
            }
            .kiss-floating-toolbar .kiss-results {
                margin-top: 12px;
                max-height: 400px;
                overflow-y: auto;
                border-top: 1px solid #e0e0e0;
                padding-top: 12px;
                display: none;
            }
            .kiss-floating-toolbar .kiss-results.has-results {
                display: block;
            }
            .kiss-floating-toolbar .kiss-result-item {
                padding: 10px;
                border: 1px solid #e0e0e0;
                border-radius: 3px;
                margin-bottom: 8px;
                background: #f9f9f9;
            }
            .kiss-floating-toolbar .kiss-result-item:hover {
                background: #f0f6fc;
                border-color: #2271b1;
            }
            .kiss-floating-toolbar .kiss-result-name {
                font-weight: 600;
                font-size: 13px;
                color: #1d2327;
            }
            .kiss-floating-toolbar .kiss-result-email {
                font-size: 12px;
                color: #646970;
            }
            .kiss-floating-toolbar .kiss-result-meta {
                font-size: 11px;
                color: #8c8f94;
                margin-top: 4px;
            }
            .kiss-floating-toolbar .kiss-result-actions {
                margin-top: 6px;
            }
            .kiss-floating-toolbar .kiss-result-actions a {
                font-size: 11px;
                margin-right: 10px;
            }
            .kiss-floating-toolbar .kiss-no-results {
                color: #646970;
                font-style: italic;
                padding: 10px 0;
            }
            .kiss-floating-toolbar .kiss-section-title {
                font-size: 11px;
                font-weight: 600;
                color: #646970;
                text-transform: uppercase;
                margin: 12px 0 8px 0;
                padding-bottom: 4px;
                border-bottom: 1px solid #e0e0e0;
            }
            .kiss-floating-toolbar .kiss-toggle-btn {
                position: absolute;
                top: 8px;
                right: 8px;
                background: none;
                border: none;
                cursor: pointer;
                font-size: 16px;
                color: #646970;
                padding: 4px;
            }
            .kiss-floating-toolbar .kiss-toggle-btn:hover {
                color: #1d2327;
            }
            .kiss-floating-toolbar.is-collapsed .kiss-toolbar-content {
                display: none;
            }
            .kiss-floating-toolbar.is-collapsed {
                min-width: auto;
                padding: 8px 12px;
            }
            .kiss-floating-toolbar.is-collapsed .kiss-drag-handle {
                display: none;
            }
        </style>
        <?php
    }

    /**
     * Output JavaScript.
     */
    public function output_js() {
        if ( ! $this->should_show_toolbar() ) {
            return;
        }
        ?>
        <script>
        (function($) {
            'use strict';

            var toolbar = document.getElementById('kiss-floating-toolbar');
            var input = document.getElementById('kiss-floating-search-input');
            var submitBtn = document.getElementById('kiss-floating-search-submit');
            var statusEl = document.getElementById('kiss-floating-search-status');
            var resultsEl = document.getElementById('kiss-floating-results');
            var toggleBtn = document.getElementById('kiss-floating-toggle');

            if (!toolbar || !input || !submitBtn) {
                return;
            }

            /**
             * Escape HTML to prevent XSS.
             */
            function escapeHtml(text) {
                if (!text) return '';
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
            }

            /**
             * Perform KISS search via AJAX.
             */
            function handleSearch() {
                var searchTerm = input.value.trim();
                if (!searchTerm || searchTerm.length < 2) {
                    input.focus();
                    statusEl.textContent = 'Enter at least 2 characters';
                    return;
                }

                statusEl.textContent = 'Searching...';
                resultsEl.innerHTML = '';
                resultsEl.classList.remove('has-results');

                $.ajax({
                    url: KISSCOS.ajax_url,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'kiss_woo_customer_search',
                        nonce: KISSCOS.nonce,
                        q: searchTerm
                    }
                }).done(function(resp) {
                    if (!resp || !resp.success) {
                        var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Search failed';
                        statusEl.textContent = msg;
                        return;
                    }
                    renderResults(resp.data);
                    statusEl.textContent = '';
                }).fail(function() {
                    statusEl.textContent = 'Request failed. Please try again.';
                });
            }

            /**
             * Render search results.
             */
            function renderResults(data) {
                var customers = data.customers || [];
                var guestOrders = data.guest_orders || [];
                var html = '';

                if (!customers.length && !guestOrders.length) {
                    html = '<div class="kiss-no-results">No matching customers found.</div>';
                    resultsEl.innerHTML = html;
                    resultsEl.classList.add('has-results');
                    return;
                }

                if (customers.length) {
                    html += '<div class="kiss-section-title">Customers (' + customers.length + ')</div>';
                    customers.forEach(function(cust) {
                        html += '<div class="kiss-result-item">';
                        html += '<div class="kiss-result-name">' + escapeHtml(cust.name || '(No name)') + '</div>';
                        html += '<div class="kiss-result-email">' + escapeHtml(cust.email) + '</div>';
                        html += '<div class="kiss-result-meta">';
                        html += 'ID: ' + escapeHtml(cust.id);
                        if (cust.orders) {
                            html += ' &middot; Orders: ' + escapeHtml(cust.orders);
                        }
                        if (cust.registered_h) {
                            html += ' &middot; Since: ' + escapeHtml(cust.registered_h);
                        }
                        html += '</div>';
                        html += '<div class="kiss-result-actions">';
                        if (cust.edit_url) {
                            html += '<a href="' + escapeHtml(cust.edit_url) + '" target="_blank">View User</a>';
                        }
                        if (cust.orders_list && cust.orders_list.length && cust.orders_list[0].view_url) {
                            html += '<a href="' + escapeHtml(cust.orders_list[0].view_url) + '" target="_blank">Latest Order</a>';
                        }
                        html += '</div>';
                        html += '</div>';
                    });
                }

                if (guestOrders.length) {
                    html += '<div class="kiss-section-title">Guest Orders (' + guestOrders.length + ')</div>';
                    guestOrders.forEach(function(order) {
                        html += '<div class="kiss-result-item">';
                        html += '<div class="kiss-result-name">Order #' + escapeHtml(order.number || order.id) + '</div>';
                        html += '<div class="kiss-result-email">' + escapeHtml(order.billing_email) + '</div>';
                        html += '<div class="kiss-result-meta">';
                        html += escapeHtml(order.status_label) + ' &middot; ' + order.total;
                        if (order.date) {
                            html += ' &middot; ' + escapeHtml(order.date);
                        }
                        html += '</div>';
                        html += '<div class="kiss-result-actions">';
                        if (order.view_url) {
                            html += '<a href="' + escapeHtml(order.view_url) + '" target="_blank">View Order</a>';
                        }
                        html += '</div>';
                        html += '</div>';
                    });
                }

                resultsEl.innerHTML = html;
                resultsEl.classList.add('has-results');
            }

            // Event handlers
            submitBtn.addEventListener('click', handleSearch);

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleSearch();
                }
            });

            // Toggle collapse
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    toolbar.classList.toggle('is-collapsed');
                    toggleBtn.textContent = toolbar.classList.contains('is-collapsed') ? '+' : '−';
                });
            }

            // Bounds checking - keep toolbar on screen
            var PADDING = 20;

            function keepInBounds() {
                var rect = toolbar.getBoundingClientRect();
                var viewportWidth = window.innerWidth;
                var viewportHeight = window.innerHeight;
                var needsUpdate = false;
                var newLeft = rect.left;
                var newTop = rect.top;

                // Check right edge
                if (rect.right > viewportWidth - PADDING) {
                    newLeft = viewportWidth - rect.width - PADDING;
                    needsUpdate = true;
                }
                // Check left edge
                if (rect.left < PADDING) {
                    newLeft = PADDING;
                    needsUpdate = true;
                }
                // Check bottom edge
                if (rect.bottom > viewportHeight - PADDING) {
                    newTop = viewportHeight - rect.height - PADDING;
                    needsUpdate = true;
                }
                // Check top edge (account for admin bar ~32px)
                if (rect.top < 32 + PADDING) {
                    newTop = 32 + PADDING;
                    needsUpdate = true;
                }

                if (needsUpdate) {
                    toolbar.style.left = Math.max(PADDING, newLeft) + 'px';
                    toolbar.style.top = Math.max(32 + PADDING, newTop) + 'px';
                    toolbar.style.right = 'auto';
                }
            }

            // Keep in bounds on window resize
            window.addEventListener('resize', function() {
                keepInBounds();
            });

            // Draggable functionality with bounds
            (function initDraggable() {
                var dragHandle = toolbar.querySelector('.kiss-drag-handle');
                var dragTarget = dragHandle || toolbar;
                var startX, startY, startLeft, startTop;

                dragTarget.addEventListener('mousedown', function(e) {
                    if (e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON' || e.target.tagName === 'A') {
                        return;
                    }

                    e.preventDefault();
                    toolbar.classList.add('is-dragging');

                    var rect = toolbar.getBoundingClientRect();
                    startX = e.clientX;
                    startY = e.clientY;
                    startLeft = rect.left;
                    startTop = rect.top;

                    document.addEventListener('mousemove', onDrag);
                    document.addEventListener('mouseup', onDragEnd);
                });

                function onDrag(e) {
                    var deltaX = e.clientX - startX;
                    var deltaY = e.clientY - startY;

                    var newLeft = startLeft + deltaX;
                    var newTop = startTop + deltaY;

                    var rect = toolbar.getBoundingClientRect();
                    var viewportWidth = window.innerWidth;
                    var viewportHeight = window.innerHeight;

                    // Clamp to viewport bounds
                    newLeft = Math.max(PADDING, Math.min(newLeft, viewportWidth - rect.width - PADDING));
                    newTop = Math.max(32 + PADDING, Math.min(newTop, viewportHeight - rect.height - PADDING));

                    toolbar.style.left = newLeft + 'px';
                    toolbar.style.top = newTop + 'px';
                    toolbar.style.right = 'auto';
                }

                function onDragEnd() {
                    toolbar.classList.remove('is-dragging');
                    document.removeEventListener('mousemove', onDrag);
                    document.removeEventListener('mouseup', onDragEnd);
                    keepInBounds(); // Final check after drag ends
                }
            })();

        })(jQuery);
        </script>
        <?php
    }

    /**
     * Render the floating toolbar HTML.
     */
    public function render_toolbar() {
        if ( ! $this->should_show_toolbar() ) {
            return;
        }
        ?>
        <div id="kiss-floating-toolbar" class="kiss-floating-toolbar">
            <button type="button" id="kiss-floating-toggle" class="kiss-toggle-btn" title="<?php esc_attr_e( 'Toggle', 'kiss-woo-customer-order-search' ); ?>">−</button>
            <div class="kiss-drag-handle"><?php esc_html_e( '⋮⋮ Drag to move', 'kiss-woo-customer-order-search' ); ?></div>
            <div class="kiss-toolbar-content">
                <div class="kiss-toolbar-label">
                    <?php esc_html_e( 'Customer Fast Search', 'kiss-woo-customer-order-search' ); ?>
                    <span class="kiss-speed-badge"><?php esc_html_e( '3-4x faster', 'kiss-woo-customer-order-search' ); ?></span>
                </div>
                <div class="kiss-search-row">
                    <input
                        type="text"
                        id="kiss-floating-search-input"
                        class="kiss-search-input"
                        placeholder="<?php esc_attr_e( 'Email or name...', 'kiss-woo-customer-order-search' ); ?>"
                        autocomplete="off"
                    />
                    <button
                        type="button"
                        id="kiss-floating-search-submit"
                        class="button button-primary kiss-search-submit"
                    >
                        <?php esc_html_e( 'Search', 'kiss-woo-customer-order-search' ); ?>
                    </button>
                </div>
                <div id="kiss-floating-search-status" class="kiss-search-status"></div>
                <div id="kiss-floating-results" class="kiss-results"></div>
            </div>
        </div>
        <?php
    }
}

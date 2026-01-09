<?php
/**
 * Debug panel admin page.
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_Debug_Panel {

    /**
     * Register admin page.
     */
    public function register(): void {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'wp_ajax_kiss_woo_debug_get_traces', array( $this, 'ajax_get_traces' ) );
        add_action( 'wp_ajax_kiss_woo_debug_clear_traces', array( $this, 'ajax_clear_traces' ) );
    }

    /**
     * Add submenu page under WooCommerce.
     */
    public function add_menu_page(): void {
        // Only show if debug mode is enabled.
        if ( ! defined( 'KISS_WOO_FAST_SEARCH_DEBUG' ) || ! KISS_WOO_FAST_SEARCH_DEBUG ) {
            return;
        }

        add_submenu_page(
            'woocommerce',
            __( 'KISS Search Debug', 'kiss-woo-customer-order-search' ),
            __( 'Search Debug', 'kiss-woo-customer-order-search' ),
            'manage_options',
            'kiss-woo-debug',
            array( $this, 'render_page' )
        );
    }

    /**
     * Render debug panel page.
     */
    public function render_page(): void {
        $nonce = wp_create_nonce( 'kiss_woo_debug' );
        ?>
        <div class="wrap kiss-woo-debug-panel">
            <h1><?php esc_html_e( 'KISS Search Debug Panel', 'kiss-woo-customer-order-search' ); ?></h1>

            <div class="kiss-debug-controls">
                <button type="button" class="button button-primary" id="kiss-debug-refresh">
                    <?php esc_html_e( 'Refresh', 'kiss-woo-customer-order-search' ); ?>
                </button>
                <button type="button" class="button" id="kiss-debug-clear">
                    <?php esc_html_e( 'Clear History', 'kiss-woo-customer-order-search' ); ?>
                </button>
                <label>
                    <input type="checkbox" id="kiss-debug-auto-refresh" />
                    <?php esc_html_e( 'Auto-refresh (5s)', 'kiss-woo-customer-order-search' ); ?>
                </label>
            </div>

            <div class="kiss-debug-status">
                <h3><?php esc_html_e( 'System Status', 'kiss-woo-customer-order-search' ); ?></h3>
                <table class="widefat">
                    <tr>
                        <th><?php esc_html_e( 'Sequential Order Numbers Pro', 'kiss-woo-customer-order-search' ); ?></th>
                        <td>
                            <?php if ( function_exists( 'wc_sequential_order_numbers' ) ) : ?>
                                <span class="kiss-status-ok">✓ <?php esc_html_e( 'Active', 'kiss-woo-customer-order-search' ); ?></span>
                            <?php else : ?>
                                <span class="kiss-status-warn">○ <?php esc_html_e( 'Not Active', 'kiss-woo-customer-order-search' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'HPOS Enabled', 'kiss-woo-customer-order-search' ); ?></th>
                        <td>
                            <?php
                            $hpos_enabled = class_exists( 'KISS_Woo_Utils' )
                                ? KISS_Woo_Utils::is_hpos_enabled()
                                : false;
                            ?>
                            <?php if ( $hpos_enabled ) : ?>
                                <span class="kiss-status-ok">✓ <?php esc_html_e( 'Enabled', 'kiss-woo-customer-order-search' ); ?></span>
                            <?php else : ?>
                                <span class="kiss-status-info">○ <?php esc_html_e( 'Legacy Mode', 'kiss-woo-customer-order-search' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Cache Status', 'kiss-woo-customer-order-search' ); ?></th>
                        <td>
                            <?php esc_html_e( 'Active (5 min TTL)', 'kiss-woo-customer-order-search' ); ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="kiss-debug-traces">
                <h3><?php esc_html_e( 'Request History', 'kiss-woo-customer-order-search' ); ?></h3>
                <div id="kiss-debug-traces-container">
                    <p class="loading"><?php esc_html_e( 'Loading...', 'kiss-woo-customer-order-search' ); ?></p>
                </div>
            </div>
        </div>

        <?php $this->render_styles(); ?>
        <?php $this->render_scripts( $nonce ); ?>
        <?php
    }

    /**
     * Render inline styles.
     */
    private function render_styles(): void {
        ?>
        <style>
            .kiss-woo-debug-panel { max-width: 1200px; }
            .kiss-debug-controls { margin: 20px 0; display: flex; gap: 10px; align-items: center; }
            .kiss-debug-status { margin: 20px 0; }
            .kiss-debug-status table { max-width: 500px; }
            .kiss-status-ok { color: #46b450; }
            .kiss-status-warn { color: #ffb900; }
            .kiss-status-info { color: #00a0d2; }
            .kiss-debug-traces { margin: 20px 0; }
            .kiss-debug-request {
                background: #fff;
                border: 1px solid #ccd0d4;
                margin-bottom: 10px;
                border-radius: 4px;
            }
            .kiss-debug-request-header {
                padding: 10px 15px;
                background: #f8f9fa;
                border-bottom: 1px solid #ccd0d4;
                cursor: pointer;
                display: flex;
                justify-content: space-between;
            }
            .kiss-debug-request-header:hover { background: #f0f0f1; }
            .kiss-debug-request-body { padding: 15px; display: none; }
            .kiss-debug-request.expanded .kiss-debug-request-body { display: block; }
            .kiss-trace-item {
                font-family: monospace;
                font-size: 12px;
                padding: 5px 10px;
                border-left: 3px solid #ccc;
                margin: 5px 0;
            }
            .kiss-trace-item.level-info { border-color: #00a0d2; }
            .kiss-trace-item.level-warn { border-color: #ffb900; }
            .kiss-trace-item.level-error { border-color: #dc3232; }
            .kiss-trace-time { color: #666; }
            .kiss-trace-component { color: #0073aa; font-weight: bold; }
            .kiss-trace-action { color: #23282d; }
            .kiss-trace-context { color: #666; margin-left: 20px; }
        </style>
        <?php
    }

    /**
     * Render inline JavaScript.
     *
     * @param string $nonce Security nonce.
     */
    private function render_scripts( string $nonce ): void {
        ?>
        <script>
        jQuery(function($) {
            var autoRefreshInterval = null;
            var nonce = <?php echo wp_json_encode( $nonce ); ?>;

            function loadTraces() {
                $.post(ajaxurl, {
                    action: 'kiss_woo_debug_get_traces',
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        renderTraces(response.data);
                    }
                });
            }

            function renderTraces(history) {
                var $container = $('#kiss-debug-traces-container');

                if (!history || Object.keys(history).length === 0) {
                    $container.html('<p><?php echo esc_js( __( 'No trace history. Perform a search to see traces.', 'kiss-woo-customer-order-search' ) ); ?></p>');
                    return;
                }

                var html = '';
                var keys = Object.keys(history).reverse();

                keys.forEach(function(requestId) {
                    var request = history[requestId];
                    html += '<div class="kiss-debug-request" data-id="' + requestId + '">';
                    html += '<div class="kiss-debug-request-header">';
                    html += '<span><strong>' + request.method + '</strong> ' + escapeHtml(request.url) + '</span>';
                    html += '<span>' + request.total_ms + 'ms - ' + request.timestamp + '</span>';
                    html += '</div>';
                    html += '<div class="kiss-debug-request-body">';

                    request.traces.forEach(function(trace) {
                        html += '<div class="kiss-trace-item level-' + trace.level + '">';
                        html += '<span class="kiss-trace-time">[' + trace.elapsed_ms + 'ms]</span> ';
                        html += '<span class="kiss-trace-component">' + trace.component + '</span>';
                        html += '::<span class="kiss-trace-action">' + trace.action + '</span>';
                        if (trace.context && Object.keys(trace.context).length > 0) {
                            html += '<div class="kiss-trace-context">' + escapeHtml(JSON.stringify(trace.context)) + '</div>';
                        }
                        html += '</div>';
                    });

                    html += '</div></div>';
                });

                $container.html(html);
            }

            function escapeHtml(text) {
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            $(document).on('click', '.kiss-debug-request-header', function() {
                $(this).closest('.kiss-debug-request').toggleClass('expanded');
            });

            $('#kiss-debug-refresh').on('click', loadTraces);

            $('#kiss-debug-clear').on('click', function() {
                $.post(ajaxurl, {
                    action: 'kiss_woo_debug_clear_traces',
                    nonce: nonce
                }, function() {
                    loadTraces();
                });
            });

            $('#kiss-debug-auto-refresh').on('change', function() {
                if ($(this).is(':checked')) {
                    autoRefreshInterval = setInterval(loadTraces, 5000);
                } else {
                    clearInterval(autoRefreshInterval);
                }
            });

            loadTraces();
        });
        </script>
        <?php
    }

    /**
     * AJAX handler to get traces.
     */
    public function ajax_get_traces(): void {
        check_ajax_referer( 'kiss_woo_debug', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        wp_send_json_success( KISS_Woo_Debug_Tracer::get_history() );
    }

    /**
     * AJAX handler to clear traces.
     */
    public function ajax_clear_traces(): void {
        check_ajax_referer( 'kiss_woo_debug', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        KISS_Woo_Debug_Tracer::clear_history();
        wp_send_json_success();
    }
}

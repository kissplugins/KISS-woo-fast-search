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
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
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
     * Enqueue CSS and JS for debug panel.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( string $hook ): void {
        if ( 'woocommerce_page_kiss-woo-debug' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'kiss-woo-debug',
            KISS_WOO_COS_URL . 'admin/css/kiss-woo-debug.css',
            array(),
            KISS_WOO_COS_VERSION
        );

        wp_enqueue_script(
            'kiss-woo-debug',
            KISS_WOO_COS_URL . 'admin/js/kiss-woo-debug.js',
            array( 'jquery' ),
            KISS_WOO_COS_VERSION,
            true
        );

        wp_localize_script(
            'kiss-woo-debug',
            'kissWooDebug',
            array(
                'nonce' => wp_create_nonce( 'kiss_woo_debug' ),
                'i18n'  => array(
                    'noTraces' => __( 'No trace history. Perform a search to see traces.', 'kiss-woo-customer-order-search' ),
                ),
            )
        );
    }

    /**
     * Render debug panel page.
     */
    public function render_page(): void {
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

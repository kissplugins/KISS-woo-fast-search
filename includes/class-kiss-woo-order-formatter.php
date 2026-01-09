<?php
/**
 * Centralized order formatting for API responses.
 *
 * SINGLE WRITE PATH: All order-to-array conversion goes through format().
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_Order_Formatter {

    /**
     * Format an order for JSON/API output.
     *
     * @param WC_Order $order The order to format.
     * @return array
     */
    public static function format( WC_Order $order ): array {
        $order_id = $order->get_id();

        // HPOS-compatible edit URL.
        $edit_url = self::get_edit_url( $order_id );

        return array(
            'id'            => $order_id,
            'order_number'  => $order->get_order_number(),
            'status'        => $order->get_status(),
            'status_label'  => wc_get_order_status_name( $order->get_status() ),
            'total'         => $order->get_total(),
            'total_display' => html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ),
            'currency'      => $order->get_currency(),
            'date_created'  => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : null,
            'date_display'  => $order->get_date_created() ? $order->get_date_created()->format( get_option( 'date_format' ) ) : '',
            'customer'      => array(
                'name'  => self::get_customer_name( $order ),
                'email' => $order->get_billing_email(),
            ),
            'view_url'      => esc_url( $edit_url ),
        );
    }

    /**
     * Get HPOS-compatible edit URL.
     *
     * @param int $order_id The order ID.
     * @return string
     */
    private static function get_edit_url( int $order_id ): string {
        // Get the order object to use WooCommerce's built-in method.
        $order = wc_get_order( $order_id );

        // Use WooCommerce's get_edit_order_url() method (HPOS-aware).
        // This method exists in WC_Order and handles both HPOS and legacy modes.
        if ( $order && method_exists( $order, 'get_edit_order_url' ) ) {
            return $order->get_edit_order_url();
        }

        // Fallback 1: Try get_edit_post_link (for legacy/non-HPOS).
        $edit_url = get_edit_post_link( $order_id, 'raw' );

        if ( ! empty( $edit_url ) ) {
            return $edit_url;
        }

        // Fallback 2: Construct URL manually.
        // Check if HPOS is enabled to determine the correct URL format.
        if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) &&
             \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
            // HPOS mode: Use admin.php with page=wc-orders.
            return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
        }

        // Legacy mode: Use post.php.
        return admin_url( 'post.php?post=' . $order_id . '&action=edit' );
    }

    /**
     * Get formatted customer name.
     *
     * @param WC_Order $order The order.
     * @return string
     */
    private static function get_customer_name( WC_Order $order ): string {
        $first = $order->get_billing_first_name();
        $last  = $order->get_billing_last_name();

        $name = trim( $first . ' ' . $last );

        if ( empty( $name ) ) {
            $name = __( 'Guest', 'kiss-woo-customer-order-search' );
        }

        return $name;
    }

    /**
     * Format multiple orders.
     *
     * @param WC_Order[] $orders Array of orders.
     * @return array
     */
    public static function format_many( array $orders ): array {
        return array_map( array( self::class, 'format' ), $orders );
    }
}


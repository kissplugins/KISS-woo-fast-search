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
            'view_url'      => $edit_url, // Don't escape - already safe from admin_url() and will be used in JavaScript
        );
    }

    /**
     * Format a raw order row (from direct SQL queries) to the same output shape as format().
     *
     * @param array $data
     * @return array
     */
    public static function format_from_raw( array $data ): array {
        $order_id = isset( $data['id'] ) ? (int) $data['id'] : 0;

        $status = isset( $data['status'] ) ? (string) $data['status'] : '';
        if ( 0 === strpos( $status, 'wc-' ) ) {
            $status = substr( $status, 3 );
        }

        $status_label = $status;
        if ( function_exists( 'wc_get_order_status_name' ) ) {
            $status_label = wc_get_order_status_name( $status );
        }

        $currency = isset( $data['currency'] ) ? (string) $data['currency'] : '';
        $total    = isset( $data['total'] ) ? $data['total'] : '';

        $total_display = '';
        if ( '' !== $total && function_exists( 'wc_price' ) ) {
            $total_display = html_entity_decode( wp_strip_all_tags( wc_price( $total, array( 'currency' => $currency ) ) ), ENT_QUOTES, 'UTF-8' );
        } elseif ( '' !== $total ) {
            $total_display = trim( $currency . ' ' . number_format( (float) $total, 2 ) );
        }

        $date_created = null;
        $date_display = '';
        if ( ! empty( $data['date_gmt'] ) && '0000-00-00 00:00:00' !== $data['date_gmt'] ) {
            $timestamp = strtotime( (string) $data['date_gmt'] );
            if ( $timestamp ) {
                $ts_local     = $timestamp + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
                $date_created = date_i18n( 'Y-m-d H:i:s', $ts_local );
                $date_display = date_i18n( get_option( 'date_format' ), $ts_local );
            }
        }

        $view_url = self::get_edit_url( $order_id );

        return array(
            'id'            => $order_id,
            'order_number'  => isset( $data['order_number'] ) ? (string) $data['order_number'] : (string) $order_id,
            'status'        => $status,
            'status_label'  => $status_label,
            'total'         => $total,
            'total_display' => $total_display,
            'currency'      => $currency,
            'date_created'  => $date_created,
            'date_display'  => $date_display,
            'customer'      => array(
                'name'  => isset( $data['customer_name'] ) ? (string) $data['customer_name'] : '',
                'email' => isset( $data['billing_email'] ) ? (string) $data['billing_email'] : '',
            ),
            'view_url'      => $view_url,
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
        if ( class_exists( 'KISS_Woo_Utils' ) && KISS_Woo_Utils::is_hpos_enabled() ) {
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


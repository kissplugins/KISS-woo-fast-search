<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_COS_Search {

    /**
     * Find matching customers by email or name.
     *
     * @param string $term Search term.
     *
     * @return array
     */
    public function search_customers( $term ) {
        $term = trim( $term );

        $user_query_args = array(
            'number'         => 20,
            'fields'         => 'all_with_meta',
            'orderby'        => 'registered',
            'order'          => 'DESC',
            'search'         => '*' . esc_attr( $term ) . '*',
            'search_columns' => array( 'user_email', 'user_login', 'display_name' ),
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => 'billing_email',
                    'value'   => $term,
                    'compare' => 'LIKE',
                ),
                array(
                    'key'     => 'billing_first_name',
                    'value'   => $term,
                    'compare' => 'LIKE',
                ),
                array(
                    'key'     => 'billing_last_name',
                    'value'   => $term,
                    'compare' => 'LIKE',
                ),
            ),
        );

        $user_query = new WP_User_Query( $user_query_args );
        $users      = $user_query->get_results();

        $results = array();

        if ( ! empty( $users ) ) {
            foreach ( $users as $user ) {
                $user_id   = $user->ID;
                $first     = get_user_meta( $user_id, 'billing_first_name', true );
                $last      = get_user_meta( $user_id, 'billing_last_name', true );
                $full_name = trim( $first . ' ' . $last );

                if ( '' === $full_name ) {
                    $full_name = $user->display_name;
                }

                $billing_email = get_user_meta( $user_id, 'billing_email', true );
                $primary_email = $user->user_email ? $user->user_email : $billing_email;

                $order_count = $this->get_order_count_for_customer( $user_id );

                $results[] = array(
                    'id'           => $user_id,
                    'name'         => esc_html( $full_name ),
                    'email'        => esc_html( $primary_email ),
                    'billing_email'=> esc_html( $billing_email ),
                    'registered'   => $user->user_registered,
                    'registered_h' => esc_html( $this->format_date_human( $user->user_registered ) ),
                    'orders'       => (int) $order_count,
                    'edit_url'     => esc_url( get_edit_user_link( $user_id ) ),
                    'orders_list'  => $this->get_recent_orders_for_customer( $user_id, $primary_email ),
                );
            }
        }

        return $results;
    }

    /**
     * Get total orders for a given customer ID.
     *
     * @param int $user_id
     *
     * @return int
     */
    protected function get_order_count_for_customer( $user_id ) {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return 0;
        }

        $orders = wc_get_orders(
            array(
                'customer' => $user_id,
                'limit'    => -1,
                'return'   => 'ids',
                'status'   => array_keys( wc_get_order_statuses() ),
            )
        );

        return is_array( $orders ) ? count( $orders ) : 0;
    }

    /**
     * Get recent orders for a given customer ID + email.
     * Returns simplified data suitable for JSON.
     *
     * @param int    $user_id
     * @param string $email
     *
     * @return array
     */
    protected function get_recent_orders_for_customer( $user_id, $email ) {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return array();
        }

        $args = array(
            'limit'   => 10,
            'orderby' => 'date',
            'order'   => 'DESC',
            'status'  => array_keys( wc_get_order_statuses() ),
        );

        if ( $user_id ) {
            $args['customer'] = $user_id;
        }

        $orders = wc_get_orders( $args );

        $results = array();

        if ( ! empty( $orders ) ) {
            foreach ( $orders as $order ) {
                /** @var WC_Order $order */
                $results[] = $this->format_order_for_output( $order );
            }
        }

        return $results;
    }

    /**
     * Search guest orders (no user account) by billing email.
     *
     * @param string $term
     *
     * @return array
     */
    public function search_guest_orders_by_email( $term ) {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return array();
        }

        if ( ! is_email( $term ) ) {
            // Only search guest orders when an email address is entered.
            return array();
        }

        $orders = wc_get_orders(
            array(
                'limit'         => 20,
                'orderby'       => 'date',
                'order'         => 'DESC',
                'status'        => array_keys( wc_get_order_statuses() ),
                'billing_email' => $term,
            )
        );

        $results = array();

        if ( ! empty( $orders ) ) {
            foreach ( $orders as $order ) {
                /** @var WC_Order $order */
                if ( 0 === (int) $order->get_customer_id() ) {
                    $results[] = $this->format_order_for_output( $order );
                }
            }
        }

        return $results;
    }

    /**
     * Prepare order data for JSON output.
     *
     * @param WC_Order $order
     *
     * @return array
     */
    protected function format_order_for_output( $order ) {
        $order_id     = $order->get_id();
        $status       = $order->get_status();
        $total        = $order->get_total();
        $currency     = $order->get_currency();
        $date_created = $order->get_date_created();
        $payment      = $order->get_payment_method_title();
        $shipping     = $order->get_shipping_method();

        return array(
            'id'            => (int) $order_id,
            'number'        => esc_html( $order->get_order_number() ),
            'status'        => esc_attr( $status ),
            'status_label'  => esc_html( wc_get_order_status_name( $status ) ),
            'total'         => wc_price( $total, array( 'currency' => $currency ) ),
            'date'          => esc_html( $date_created ? $date_created->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '' ),
            'payment'       => esc_html( $payment ),
            'shipping'      => esc_html( $shipping ),
            'view_url'      => esc_url( get_edit_post_link( $order_id ) ),
            'billing_email' => esc_html( $order->get_billing_email() ),
        );
    }

    /**
     * Format user registration date in a nice human style.
     *
     * @param string $mysql_date
     *
     * @return string
     */
    protected function format_date_human( $mysql_date ) {
        if ( empty( $mysql_date ) || '0000-00-00 00:00:00' === $mysql_date ) {
            return '';
        }

        $timestamp = strtotime( $mysql_date );
        if ( ! $timestamp ) {
            return $mysql_date;
        }

        return date_i18n( get_option( 'date_format' ), $timestamp );
    }
}

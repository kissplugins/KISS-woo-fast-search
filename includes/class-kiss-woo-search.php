<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_COS_Search {

    /**
     * Holds debug info from the most recent customer lookup-table search.
     *
     * @var array
     */
    protected $last_lookup_debug = array();

    /**
     * Whether debug logging is enabled.
     *
     * Default: disabled. Enable by defining `KISS_WOO_FAST_SEARCH_DEBUG` as true.
     *
     * @return bool
     */


    /**
     * Find matching customers by email or name.
     *
     * @param string $term Search term.
     *
     * @return array
     */
    public function search_customers( $term ) {
        $t0 = microtime( true );
        $term = trim( $term );

        // Prefer WooCommerce lookup table to avoid wp_usermeta OR+LIKE scans.
        $user_ids  = $this->search_user_ids_via_customer_lookup( $term, 20 );
        $used_path = ! empty( $user_ids ) ? 'wc_customer_lookup' : 'fallback_wp_user_query';

        // IMPORTANT: Avoid `fields => all_with_meta` (loads *all* usermeta). We only need a few keys.
        $user_fields = array( 'ID', 'user_email', 'display_name', 'user_registered' );

        if ( ! empty( $user_ids ) ) {
            $user_query = new WP_User_Query(
                array(
                    'include'               => $user_ids,
                    'orderby'               => 'include',
                    'fields'                => $user_fields,
                    'number'                => count( $user_ids ),
                    'count_total'           => false,
                    'update_user_meta_cache'=> false,
                )
            );
            $users = $user_query->get_results();
        } else {
            // Fallback when wc_customer_lookup is unavailable.
            // IMPORTANT: Avoid meta_query on wp_usermeta - it's O(n) on large sites.
            // Only search wp_users table columns (indexed) for acceptable performance.
            $is_email_search = ( false !== strpos( $term, '@' ) );

            $user_query_args = array(
                'number'                 => 20,
                'fields'                 => $user_fields,
                'orderby'                => 'registered',
                'order'                  => 'DESC',
                'search'                 => '*' . esc_attr( $term ) . '*',
                'search_columns'         => array( 'user_email', 'user_login', 'display_name' ),
                'update_user_meta_cache' => false,
            );

            // Only add billing_email meta search for email-like terms (much smaller result set).
            if ( $is_email_search ) {
                $user_query_args['meta_query'] = array(
                    array(
                        'key'     => 'billing_email',
                        'value'   => $term,
                        'compare' => 'LIKE',
                    ),
                );
            }
            // Name searches without wc_customer_lookup: wp_users.display_name is the only fast option.
            // Searching wp_usermeta for billing_first_name/last_name is too slow on large sites.

            $user_query = new WP_User_Query( $user_query_args );
            $users      = $user_query->get_results();
        }

        $results = array();

        if ( ! empty( $users ) ) {
            $user_ids = array_map( 'intval', wp_list_pluck( $users, 'ID' ) );
            $user_ids = array_values( array_filter( $user_ids ) );

            // Batch-fetch only the billing meta keys we actually need.
            $billing_meta = $this->get_user_meta_for_users( $user_ids, array( 'billing_first_name', 'billing_last_name', 'billing_email' ) );

            $order_counts  = $this->get_order_counts_for_customers( $user_ids );
            $recent_orders = $this->get_recent_orders_for_customers( $user_ids );

            foreach ( $users as $user ) {
                $user_id = (int) $user->ID;

                $first = isset( $billing_meta[ $user_id ]['billing_first_name'] ) ? (string) $billing_meta[ $user_id ]['billing_first_name'] : '';
                $last  = isset( $billing_meta[ $user_id ]['billing_last_name'] ) ? (string) $billing_meta[ $user_id ]['billing_last_name'] : '';
                $full_name = trim( $first . ' ' . $last );

                if ( '' === $full_name ) {
                    $full_name = isset( $user->display_name ) ? (string) $user->display_name : '';
                }

                $billing_email = isset( $billing_meta[ $user_id ]['billing_email'] ) ? (string) $billing_meta[ $user_id ]['billing_email'] : '';
                $user_email    = isset( $user->user_email ) ? (string) $user->user_email : '';
                $primary_email = $user_email ? $user_email : $billing_email;

                $registered    = isset( $user->user_registered ) ? (string) $user->user_registered : '';

                $order_count = isset( $order_counts[ $user_id ] ) ? (int) $order_counts[ $user_id ] : 0;
                $orders_list = isset( $recent_orders[ $user_id ] ) ? $recent_orders[ $user_id ] : array();

                $results[] = array(
                    'id'            => $user_id,
                    'name'          => esc_html( $full_name ),
                    'email'         => esc_html( $primary_email ),
                    'billing_email' => esc_html( $billing_email ),
                    'registered'    => $registered,
                    'registered_h'  => esc_html( $this->format_date_human( $registered ) ),
                    'orders'        => $order_count,
                    'edit_url'      => esc_url( get_edit_user_link( $user_id ) ),
                    'orders_list'   => $orders_list,
                );
            }
        }

        $elapsed_ms = ( microtime( true ) - $t0 ) * 1000;

        KISS_Woo_Debug_Tracer::log(
            'Search',
            'search_customers',
            array(
                'term'          => $term,
                'path'          => $used_path,
                'lookup_debug'  => $this->last_lookup_debug,
                'results_users' => is_array( $users ) ? count( $users ) : 0,
                'elapsed_ms'    => round( $elapsed_ms, 2 ),
            )
        );

        return $results;
    }

    /**
     * Attempt to find matching WP user IDs using WooCommerce's customer lookup table.
     *
     * This avoids expensive OR+LIKE scans across wp_usermeta.
     *
     * Notes:
     * - Uses prefix matching where possible to allow indexes to help.
     * - Only returns user_id > 0 (registered users). Guest orders are handled elsewhere.
     * - Returns an empty array if the lookup table is unavailable.
     *
     * @param string $term
     * @param int    $limit
     *
     * @return int[]
     */
    protected function search_user_ids_via_customer_lookup( $term, $limit = 20 ) {
        global $wpdb;

        $this->last_lookup_debug = array(
            'enabled' => true,
            'mode'    => null,
            'table'   => $wpdb->prefix . 'wc_customer_lookup',
            'hit'     => false,
            'count'   => 0,
        );

        $term  = trim( (string) $term );
        $limit = (int) $limit;

        if ( '' === $term || $limit <= 0 ) {
            $this->last_lookup_debug['enabled'] = false;
            return array();
        }

        // Ensure lookup table exists.
        $table  = $wpdb->prefix . 'wc_customer_lookup';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            $this->last_lookup_debug['enabled'] = false;
            KISS_Woo_Debug_Tracer::log( 'Search', 'wc_customer_lookup_missing', array( 'table' => $table, 'got' => $exists ), 'warn' );
            return array();
        }

        $like_term = $wpdb->esc_like( $term );

        // Detect "first last" input.
        $parts = preg_split( '/\s+/', $term );
        $parts = array_values( array_filter( array_map( 'trim', (array) $parts ) ) );

        // Heuristic: treat anything containing '@' as email-ish.
        $is_emailish = false !== strpos( $term, '@' );

        if ( count( $parts ) >= 2 ) {
            $this->last_lookup_debug['mode'] = 'name_pair_prefix';
            // Build LIKE patterns with wildcard, then pass through prepare().
            // Use remove_placeholder_escape() to convert WP's hash placeholders back to %.
            $a = $wpdb->esc_like( $parts[0] ) . '%';
            $b = $wpdb->esc_like( $parts[1] ) . '%';

            $sql = $wpdb->prepare(
                "SELECT user_id
                 FROM {$table}
                 WHERE user_id > 0
                 AND ((first_name LIKE %s AND last_name LIKE %s) OR (first_name LIKE %s AND last_name LIKE %s))
                 ORDER BY date_registered DESC
                 LIMIT %d",
                $a,
                $b,
                $b,
                $a,
                $limit
            );

            // WordPress 6.x escapes % as hash placeholders; convert them back.
            if ( method_exists( $wpdb, 'remove_placeholder_escape' ) ) {
                $sql = $wpdb->remove_placeholder_escape( $sql );
            }

            KISS_Woo_Debug_Tracer::log( 'Search', 'lookup_query', array( 'mode' => 'name_pair_prefix' ), 'debug' );

            $t_start   = microtime( true );
            $ids       = $wpdb->get_col( $sql );
            $t_elapsed = round( ( microtime( true ) - $t_start ) * 1000, 2 );

            KISS_Woo_Debug_Tracer::log( 'Search', 'lookup_result', array( 'mode' => 'name_pair_prefix', 'count' => is_array( $ids ) ? count( $ids ) : 0, 'elapsed_ms' => $t_elapsed ), 'debug' );
        } else {
            $this->last_lookup_debug['mode'] = 'prefix_multi_column';
            // Prefix search across indexed-ish columns.
            $prefix_pattern = $like_term . '%';
            $sql = $wpdb->prepare(
                "SELECT user_id
                 FROM {$table}
                 WHERE user_id > 0
                 AND (email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR username LIKE %s)
                 ORDER BY date_registered DESC
                 LIMIT %d",
                $prefix_pattern,
                $prefix_pattern,
                $prefix_pattern,
                $prefix_pattern,
                $limit
            );

            // WordPress 6.x escapes % as hash placeholders; convert them back.
            if ( method_exists( $wpdb, 'remove_placeholder_escape' ) ) {
                $sql = $wpdb->remove_placeholder_escape( $sql );
            }

            $ids = $wpdb->get_col( $sql );

            // If this looks like an email fragment and prefix found nothing, fall back to contains on email.
            // This is still much cheaper than wp_usermeta joins in practice.
            if ( empty( $ids ) && $is_emailish && strlen( $term ) >= 3 ) {
                $this->last_lookup_debug['mode'] = 'contains_email_fallback';
                $contains_pattern = '%' . $like_term . '%';
                $sql2 = $wpdb->prepare(
                    "SELECT user_id
                     FROM {$table}
                     WHERE user_id > 0
                     AND email LIKE %s
                     ORDER BY date_registered DESC
                     LIMIT %d",
                    $contains_pattern,
                    $limit
                );

                if ( method_exists( $wpdb, 'remove_placeholder_escape' ) ) {
                    $sql2 = $wpdb->remove_placeholder_escape( $sql2 );
                }

                $ids = $wpdb->get_col( $sql2 );
            }
        }

        $ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
        if ( empty( $ids ) ) {
            $this->last_lookup_debug['hit']   = false;
            $this->last_lookup_debug['count'] = 0;
            return array();
        }

        $this->last_lookup_debug['hit']   = true;
        $this->last_lookup_debug['count'] = count( $ids );

        return $ids;
    }

    /**
     * Fetch selected meta keys for many users using a single query.
     *
     * @param int[]   $user_ids
     * @param string[] $meta_keys
     *
     * @return array user_id => [ meta_key => meta_value ]
     */
    protected function get_user_meta_for_users( $user_ids, $meta_keys ) {
        global $wpdb;

        $user_ids = array_values( array_filter( array_map( 'intval', (array) $user_ids ) ) );
        $meta_keys = array_values( array_filter( array_map( 'strval', (array) $meta_keys ) ) );

        if ( empty( $user_ids ) || empty( $meta_keys ) ) {
            return array();
        }

        $user_placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
        $key_placeholders  = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

        $sql = $wpdb->prepare(
            "SELECT user_id, meta_key, meta_value
             FROM {$wpdb->usermeta}
             WHERE user_id IN ({$user_placeholders})
             AND meta_key IN ({$key_placeholders})",
            array_merge( $user_ids, $meta_keys )
        );

        $rows = $wpdb->get_results( $sql );

        $out = array();
        foreach ( $user_ids as $user_id ) {
            $out[ $user_id ] = array();
        }

        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                $uid = (int) $row->user_id;
                if ( ! isset( $out[ $uid ] ) ) {
                    $out[ $uid ] = array();
                }
                $out[ $uid ][ (string) $row->meta_key ] = maybe_unserialize( $row->meta_value );
            }
        }

        return $out;
    }

    /**
     * Get total orders for multiple customer IDs using a single query where possible.
     *
     * @param array $user_ids Customer IDs.
     *
     * @return array Map of user_id => order_count.
     */
    protected function get_order_counts_for_customers( $user_ids ) {
        if ( empty( $user_ids ) || ! is_array( $user_ids ) ) {
            return array();
        }

        $user_ids = array_values( array_filter( array_map( 'intval', $user_ids ) ) );

        if ( empty( $user_ids ) ) {
            return array();
        }

        $order_counts = array_fill_keys( $user_ids, 0 );

        $t_counts_start = microtime( true );

        KISS_Woo_Debug_Tracer::log( 'Search', 'get_order_counts_start', array( 'user_ids' => $user_ids ), 'debug' );

        try {
            if ( class_exists( 'KISS_Woo_Utils' ) && KISS_Woo_Utils::is_hpos_enabled() ) {
                $counts = $this->get_order_counts_hpos( $user_ids );
                KISS_Woo_Debug_Tracer::log( 'Search', 'get_order_counts_done', array( 'mode' => 'hpos', 'elapsed_ms' => round( ( microtime( true ) - $t_counts_start ) * 1000, 2 ) ), 'debug' );
                return $counts + $order_counts;
            }
        } catch ( Exception $e ) {
            KISS_Woo_Debug_Tracer::log( 'Search', 'get_order_counts_exception', array( 'message' => $e->getMessage() ), 'warn' );
        }

        $legacy_counts = $this->get_order_counts_legacy_batch( $user_ids );
        KISS_Woo_Debug_Tracer::log( 'Search', 'get_order_counts_done', array( 'mode' => 'legacy', 'elapsed_ms' => round( ( microtime( true ) - $t_counts_start ) * 1000, 2 ) ), 'debug' );

        return $legacy_counts + $order_counts;
    }

    /**
     * Batch order counts for HPOS.
     *
     * @param array $user_ids Customer IDs.
     *
     * @return array
     */
    protected function get_order_counts_hpos( $user_ids ) {
        global $wpdb;

        $user_ids = array_values( array_filter( array_map( 'intval', $user_ids ) ) );

        if ( empty( $user_ids ) ) {
            return array();
        }

        $statuses = array_keys( wc_get_order_statuses() );
        if ( empty( $statuses ) ) {
            return array();
        }

        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $user_placeholders   = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );

        $orders_table = $wpdb->prefix . 'wc_orders';

        $query = $wpdb->prepare(
            "SELECT customer_id, COUNT(*) as total FROM {$orders_table}
             WHERE customer_id IN ({$user_placeholders})
             AND status IN ({$status_placeholders})
             GROUP BY customer_id",
            array_merge( $user_ids, $statuses )
        );

        $rows = $wpdb->get_results( $query );

        $counts = array();
        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                $counts[ (int) $row->customer_id ] = (int) $row->total;
            }
        }

        return $counts;
    }

    /**
     * Get order count using legacy posts table (fallback for older WooCommerce).
     * Uses direct SQL COUNT query for performance.
     *
     * @param int $user_id Customer user ID.
     *
     * @return int Number of orders for the customer.
     */
    protected function get_order_count_legacy( $user_id ) {
        global $wpdb;

        // Validate user ID
        if ( empty( $user_id ) || ! is_numeric( $user_id ) ) {
            return 0;
        }

        // Build status list
        $statuses = array_keys( wc_get_order_statuses() );
        if ( empty( $statuses ) ) {
            return 0;
        }

        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

        // Direct SQL COUNT query using posts and postmeta tables
        $query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'shop_order'
             AND p.post_status IN ({$status_placeholders})
             AND pm.meta_key = '_customer_user'
             AND pm.meta_value = %s",
            array_merge( $statuses, array( (string) $user_id ) )
        );

        $count = $wpdb->get_var( $query );
        return $count !== null ? (int) $count : 0;
    }

    /**
     * Batch order counts using legacy posts table.
     *
     * @param array $user_ids Customer IDs.
     *
     * @return array
     */
    protected function get_order_counts_legacy_batch( $user_ids ) {
        global $wpdb;

        $user_ids = array_values( array_filter( array_map( 'intval', $user_ids ) ) );

        if ( empty( $user_ids ) ) {
            return array();
        }

        $statuses = array_keys( wc_get_order_statuses() );
        if ( empty( $statuses ) ) {
            return array();
        }

        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $user_placeholders   = implode( ',', array_fill( 0, count( $user_ids ), '%s' ) );

        $query = $wpdb->prepare(
            "SELECT pm.meta_value AS customer_id, COUNT(DISTINCT p.ID) as total
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'shop_order'
             AND p.post_status IN ({$status_placeholders})
             AND pm.meta_key = '_customer_user'
             AND pm.meta_value IN ({$user_placeholders})
             GROUP BY pm.meta_value",
            array_merge( $statuses, array_map( 'strval', $user_ids ) )
        );

        $rows = $wpdb->get_results( $query );

        $counts = array();
        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                $counts[ (int) $row->customer_id ] = (int) $row->total;
            }
        }

        return $counts;
    }

    /**
     * Fetch order data for multiple order IDs via direct SQL.
     *
     * IMPORTANT: This bypasses wc_get_orders() to avoid expensive hooks/plugins.
     * Returns data formatted via KISS_Woo_Order_Formatter::format_from_raw().
     *
     * @param int[] $order_ids Order IDs to fetch.
     *
     * @return array Map of order_id => order data array.
     */
    protected function get_order_data_via_sql( $order_ids ) {
        global $wpdb;

        $order_ids = array_values( array_filter( array_map( 'intval', (array) $order_ids ) ) );

        if ( empty( $order_ids ) ) {
            return array();
        }

        $results = array();

        // Check if HPOS is enabled.
        $use_hpos = ( class_exists( 'KISS_Woo_Utils' ) && KISS_Woo_Utils::is_hpos_enabled() );

        if ( $use_hpos ) {
            $results = $this->get_order_data_hpos( $order_ids );
        } else {
            $results = $this->get_order_data_legacy( $order_ids );
        }

        return $results;
    }

    /**
     * Fetch order data via HPOS tables (wc_orders).
     *
     * @param int[] $order_ids Order IDs.
     *
     * @return array Map of order_id => order data.
     */
    protected function get_order_data_hpos( $order_ids ) {
        global $wpdb;

        $order_ids = array_values( array_filter( array_map( 'intval', (array) $order_ids ) ) );

        if ( empty( $order_ids ) ) {
            return array();
        }

        $results = array();

        $orders_table = $wpdb->prefix . 'wc_orders';
        $addresses_table = $wpdb->prefix . 'wc_order_addresses';

        $placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

        // Main order data from wc_orders.
        $sql = $wpdb->prepare(
            "SELECT id, status, date_created_gmt, total_amount, currency, payment_method_title
             FROM {$orders_table}
             WHERE id IN ({$placeholders})",
            $order_ids
        );

        $rows = $wpdb->get_results( $sql );

        if ( empty( $rows ) ) {
            return array();
        }

        $order_data = array();
        foreach ( $rows as $row ) {
            $order_data[ (int) $row->id ] = array(
                'id'            => (int) $row->id,
                'status'        => str_replace( 'wc-', '', $row->status ),
                'date_gmt'      => $row->date_created_gmt,
                'total'         => $row->total_amount,
                'currency'      => $row->currency,
                'payment'       => $row->payment_method_title,
                'billing_email' => '',
                'shipping'      => '',
            );
        }

        // Get billing emails from wc_order_addresses.
        $sql_addr = $wpdb->prepare(
            "SELECT order_id, email
             FROM {$addresses_table}
             WHERE order_id IN ({$placeholders})
             AND address_type = 'billing'",
            $order_ids
        );

        $addr_rows = $wpdb->get_results( $sql_addr );
        if ( ! empty( $addr_rows ) ) {
            foreach ( $addr_rows as $arow ) {
                $oid = (int) $arow->order_id;
                if ( isset( $order_data[ $oid ] ) ) {
                    $order_data[ $oid ]['billing_email'] = $arow->email;
                }
            }
        }

        // Get shipping method from wc_order_operational_data or order items.
        // For simplicity, we'll fetch from order items (woocommerce_order_items).
        $items_table = $wpdb->prefix . 'woocommerce_order_items';
        $sql_ship = $wpdb->prepare(
            "SELECT order_id, order_item_name
             FROM {$items_table}
             WHERE order_id IN ({$placeholders})
             AND order_item_type = 'shipping'",
            $order_ids
        );

        $ship_rows = $wpdb->get_results( $sql_ship );
        if ( ! empty( $ship_rows ) ) {
            foreach ( $ship_rows as $srow ) {
                $oid = (int) $srow->order_id;
                if ( isset( $order_data[ $oid ] ) && empty( $order_data[ $oid ]['shipping'] ) ) {
                    $order_data[ $oid ]['shipping'] = $srow->order_item_name;
                }
            }
        }

        // Format for output using centralized formatter.
        foreach ( $order_data as $oid => $data ) {
            $results[ $oid ] = KISS_Woo_Order_Formatter::format_from_raw( array_merge( $data, array( 'order_number' => (string) $oid ) ) );
        }

        return $results;
    }

    /**
     * Fetch order data via legacy posts/postmeta tables.
     *
     * @param int[] $order_ids Order IDs.
     *
     * @return array Map of order_id => order data.
     */
    protected function get_order_data_legacy( $order_ids ) {
        global $wpdb;

        $order_ids = array_values( array_filter( array_map( 'intval', (array) $order_ids ) ) );

        if ( empty( $order_ids ) ) {
            return array();
        }

        $results = array();

        $placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

        // Main order data from wp_posts.
        $sql = $wpdb->prepare(
            "SELECT ID, post_status, post_date_gmt
             FROM {$wpdb->posts}
             WHERE ID IN ({$placeholders})
             AND post_type = 'shop_order'",
            $order_ids
        );

        $rows = $wpdb->get_results( $sql );

        if ( empty( $rows ) ) {
            return array();
        }

        $order_data = array();
        foreach ( $rows as $row ) {
            $order_data[ (int) $row->ID ] = array(
                'id'            => (int) $row->ID,
                'status'        => str_replace( 'wc-', '', $row->post_status ),
                'date_gmt'      => $row->post_date_gmt,
                'total'         => '',
                'currency'      => '',
                'payment'       => '',
                'billing_email' => '',
                'shipping'      => '',
            );
        }

        // Fetch needed meta keys in a single query.
        $meta_keys = array( '_order_total', '_order_currency', '_payment_method_title', '_billing_email' );
        $key_placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

        $sql_meta = $wpdb->prepare(
            "SELECT post_id, meta_key, meta_value
             FROM {$wpdb->postmeta}
             WHERE post_id IN ({$placeholders})
             AND meta_key IN ({$key_placeholders})",
            array_merge( $order_ids, $meta_keys )
        );

        $meta_rows = $wpdb->get_results( $sql_meta );

        if ( ! empty( $meta_rows ) ) {
            foreach ( $meta_rows as $mrow ) {
                $oid = (int) $mrow->post_id;
                if ( ! isset( $order_data[ $oid ] ) ) {
                    continue;
                }
                switch ( $mrow->meta_key ) {
                    case '_order_total':
                        $order_data[ $oid ]['total'] = $mrow->meta_value;
                        break;
                    case '_order_currency':
                        $order_data[ $oid ]['currency'] = $mrow->meta_value;
                        break;
                    case '_payment_method_title':
                        $order_data[ $oid ]['payment'] = $mrow->meta_value;
                        break;
                    case '_billing_email':
                        $order_data[ $oid ]['billing_email'] = $mrow->meta_value;
                        break;
                }
            }
        }

        // Get shipping method from order items.
        $items_table = $wpdb->prefix . 'woocommerce_order_items';
        $sql_ship = $wpdb->prepare(
            "SELECT order_id, order_item_name
             FROM {$items_table}
             WHERE order_id IN ({$placeholders})
             AND order_item_type = 'shipping'",
            $order_ids
        );

        $ship_rows = $wpdb->get_results( $sql_ship );
        if ( ! empty( $ship_rows ) ) {
            foreach ( $ship_rows as $srow ) {
                $oid = (int) $srow->order_id;
                if ( isset( $order_data[ $oid ] ) && empty( $order_data[ $oid ]['shipping'] ) ) {
                    $order_data[ $oid ]['shipping'] = $srow->order_item_name;
                }
            }
        }

        // Format for output using centralized formatter.
        foreach ( $order_data as $oid => $data ) {
            $results[ $oid ] = KISS_Woo_Order_Formatter::format_from_raw( array_merge( $data, array( 'order_number' => (string) $oid ) ) );
        }

        return $results;
    }



    /**
     * Fetch recent orders for multiple customers in one query.
     *
     * @param array $user_ids Customer IDs.
     *
     * @return array Map of user_id => array orders.
     */
    protected function get_recent_orders_for_customers( $user_ids ) {
        if ( empty( $user_ids ) || ! function_exists( 'wc_get_orders' ) ) {
            return array();
        }

        global $wpdb;

        $user_ids = array_values( array_filter( array_map( 'intval', $user_ids ) ) );

        if ( empty( $user_ids ) ) {
            return array();
        }

        $results = array();
        foreach ( $user_ids as $user_id ) {
            $results[ $user_id ] = array();
        }

        $t_orders_start = microtime( true );

        KISS_Woo_Debug_Tracer::log( 'Search', 'get_recent_orders_start', array( 'user_ids' => $user_ids ), 'debug' );

        // NOTE: Do not rely on `wc_get_orders( [ 'customer' => [ids...] ] )`.
        // Some WooCommerce versions/docs only support a single customer ID/email.
        // Instead, fetch matching order IDs with a direct legacy SQL query (IN list),
        // then hydrate those orders in one go.

        $statuses = array_keys( wc_get_order_statuses() );
        if ( empty( $statuses ) ) {
            return $results;
        }

        // Fetch more than the final per-customer cap because we apply the 10-per-customer cap in PHP.
        // (Worst case: many recent orders belong to one customer.)
        $candidate_limit = count( $user_ids ) * 10 * 5;

        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $user_placeholders   = implode( ',', array_fill( 0, count( $user_ids ), '%s' ) );

        $sql = $wpdb->prepare(
            "SELECT p.ID AS order_id, pm.meta_value AS customer_id
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm
                ON p.ID = pm.post_id
               AND pm.meta_key = '_customer_user'
             WHERE p.post_type = 'shop_order'
               AND p.post_status IN ({$status_placeholders})
               AND pm.meta_value IN ({$user_placeholders})
             ORDER BY p.post_date_gmt DESC
             LIMIT %d",
            array_merge( $statuses, array_map( 'strval', $user_ids ), array( (int) $candidate_limit ) )
        );

        $t_sql_start = microtime( true );
        $rows = $wpdb->get_results( $sql );
        $t_sql_elapsed = round( ( microtime( true ) - $t_sql_start ) * 1000, 2 );

        KISS_Woo_Debug_Tracer::log( 'Search', 'get_recent_orders_sql_done', array( 'rows' => is_array( $rows ) ? count( $rows ) : 0, 'elapsed_ms' => $t_sql_elapsed ), 'debug' );

        if ( empty( $rows ) ) {
            return $results;
        }

        $order_ids_by_customer = array();
        foreach ( $user_ids as $user_id ) {
            $order_ids_by_customer[ $user_id ] = array();
        }

        $all_order_ids = array();

        foreach ( $rows as $row ) {
            $order_id    = (int) $row->order_id;
            $customer_id = (int) $row->customer_id;

            if ( empty( $order_id ) || empty( $customer_id ) || ! isset( $order_ids_by_customer[ $customer_id ] ) ) {
                continue;
            }

            if ( count( $order_ids_by_customer[ $customer_id ] ) >= 10 ) {
                continue;
            }

            $order_ids_by_customer[ $customer_id ][] = $order_id;
            $all_order_ids[]                          = $order_id;
        }

        if ( empty( $all_order_ids ) ) {
            return $results;
        }

        KISS_Woo_Debug_Tracer::log( 'Search', 'order_hydration_start', array( 'order_ids' => count( $all_order_ids ) ), 'debug' );

        // IMPORTANT: Avoid wc_get_orders() - it triggers expensive hooks/plugins on large sites.
        // Use direct SQL to fetch only the fields we need for display.
        $t_hydrate_start = microtime( true );
        $order_data = $this->get_order_data_via_sql( $all_order_ids );
        $t_hydrate_elapsed = round( ( microtime( true ) - $t_hydrate_start ) * 1000, 2 );

        KISS_Woo_Debug_Tracer::log( 'Search', 'order_hydration_done', array( 'orders' => is_array( $order_data ) ? count( $order_data ) : 0, 'elapsed_ms' => $t_hydrate_elapsed ), 'debug' );

        if ( empty( $order_data ) ) {
            return $results;
        }

        foreach ( $order_ids_by_customer as $customer_id => $order_ids ) {
            if ( empty( $order_ids ) ) {
                continue;
            }

            foreach ( $order_ids as $order_id ) {
                if ( ! isset( $order_data[ $order_id ] ) ) {
                    continue;
                }
                $results[ $customer_id ][] = $order_data[ $order_id ];
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
                    $results[] = KISS_Woo_Order_Formatter::format( $order );
                }
            }
        }

        return $results;
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

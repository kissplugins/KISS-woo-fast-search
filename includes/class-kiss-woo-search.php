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
     * Search term normalizer
     *
     * @var Hypercart_Search_Term_Normalizer
     */
    protected $normalizer;

    /**
     * Strategy selector
     *
     * @var Hypercart_Search_Strategy_Selector
     */
    protected $strategy_selector;

    /**
     * Memory monitor
     *
     * @var Hypercart_Memory_Monitor
     */
    protected $memory_monitor;

    /**
     * Query monitor (Phase 3)
     *
     * @var Hypercart_Query_Monitor
     */
    protected $query_monitor;

    /**
     * Search cache (Phase 3)
     *
     * @var Hypercart_Search_Cache
     */
    protected $cache;

    /**
     * Order formatter (Phase 3)
     *
     * @var Hypercart_Order_Formatter
     */
    protected $order_formatter;

    /**
     * Constructor - Initialize refactored components
     */
    public function __construct() {
        // Phase 2: Search strategies
        $this->normalizer         = new Hypercart_Search_Term_Normalizer();
        $this->strategy_selector  = new Hypercart_Search_Strategy_Selector();
        $this->memory_monitor     = new Hypercart_Memory_Monitor( 50 * 1024 * 1024 ); // 50MB limit

        // Phase 3: Optimization
        $this->query_monitor      = new Hypercart_Query_Monitor( 10 ); // 10 query limit
        $this->cache              = new Hypercart_Search_Cache( 300, true ); // 5 min cache
        $this->order_formatter    = new Hypercart_Order_Formatter();

        // Register search strategies (in priority order)
        $this->strategy_selector->register( new Hypercart_Customer_Lookup_Strategy() );
        $this->strategy_selector->register( new Hypercart_WP_User_Query_Strategy() );
    }

    /**
     * Whether debug logging is enabled.
     *
     * Default: disabled. Enable by defining `KISS_WOO_COS_DEBUG` as true.
     *
     * @return bool
     */
    protected function is_debug_enabled() {
        if ( defined( 'KISS_WOO_COS_DEBUG' ) ) {
            return (bool) KISS_WOO_COS_DEBUG;
        }

        return false;
    }

    /**
     * Log debug information.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    protected function debug_log( $message, $context = array() ) {
        if ( ! $this->is_debug_enabled() ) {
            return;
        }

        $line = '[KISS_WOO_COS] ' . (string) $message;
        if ( ! empty( $context ) ) {
            $encoded = wp_json_encode( $context );
            if ( $encoded ) {
                $line .= ' ' . $encoded;
            }
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( $line );
    }

    /**
     * Find matching customers by email or name.
     *
     * REFACTORED (Phase 2): Strategy pattern with memory monitoring
     * OPTIMIZED (Phase 3): Caching and query monitoring
     *
     * @param string $term Search term.
     *
     * @return array
     */
    public function search_customers( $term ) {
        $t0 = microtime( true );
        $term = trim( $term );

        // Phase 3: Check cache first
        $cache_key = $this->cache->get_search_key( $term, 'customers' );
        $cached    = $this->cache->get( $cache_key );

        if ( null !== $cached ) {
            $this->debug_log(
                'search_customers_cache_hit',
                array(
                    'term'       => $term,
                    'cache_key'  => $cache_key,
                    'elapsed_ms' => round( ( microtime( true ) - $t0 ) * 1000, 2 ),
                )
            );
            return $cached;
        }

        // Phase 3: Reset monitors
        $this->memory_monitor->reset();
        $this->query_monitor->reset();

        // Normalize search term
        $normalized = $this->normalizer->normalize( $term );

        // Validate term
        if ( ! $this->normalizer->is_valid( $normalized ) ) {
            return array();
        }

        // Select best available strategy
        $strategy = $this->strategy_selector->select();

        if ( ! $strategy ) {
            $this->debug_log( 'search_customers_error', array( 'error' => 'No search strategy available' ) );
            return array();
        }

        $used_path = $strategy->get_name();

        // Execute search with monitoring
        try {
            $this->memory_monitor->check(); // Check before search
            $this->query_monitor->log_query( 'strategy_search', array( 'strategy' => $used_path ) );

            $user_ids = $strategy->search( $normalized, 20 );

            $this->memory_monitor->check(); // Check after search
            $this->query_monitor->check(); // Check query count

            // Store debug info if available
            if ( method_exists( $strategy, 'get_last_debug' ) ) {
                $this->last_lookup_debug = $strategy->get_last_debug();
            }
        } catch ( Exception $e ) {
            $this->debug_log(
                'search_customers_error',
                array(
                    'error'   => $e->getMessage(),
                    'memory'  => $this->memory_monitor->get_stats(),
                    'queries' => $this->query_monitor->get_stats(),
                )
            );
            return array();
        }

        // IMPORTANT: Avoid `fields => all_with_meta` (loads *all* usermeta). We only need a few keys.
        $user_fields = array( 'ID', 'user_email', 'display_name', 'user_registered' );

        if ( empty( $user_ids ) ) {
            return array();
        }

        // Hydrate user objects (batch operation)
        $user_query = new WP_User_Query(
            array(
                'include'                => $user_ids,
                'orderby'                => 'include',
                'fields'                 => $user_fields,
                'number'                 => count( $user_ids ),
                'count_total'            => false,
                'update_user_meta_cache' => false,
            )
        );
        $users = $user_query->get_results();

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

        // Phase 3: Cache results
        $this->cache->set( $cache_key, $results );

        // Phase 3: Enhanced debug logging
        $this->debug_log(
            'search_customers',
            array(
                'term'          => $term,
                'path'          => $used_path,
                'lookup_debug'  => $this->last_lookup_debug,
                'results_users' => is_array( $users ) ? count( $users ) : 0,
                'elapsed_ms'    => round( $elapsed_ms, 2 ),
                'memory_stats'  => $this->memory_monitor->get_stats(),
                'query_stats'   => $this->query_monitor->get_stats(),
                'cached'        => false,
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
        $table = $wpdb->prefix . 'wc_customer_lookup';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            $this->last_lookup_debug['enabled'] = false;
            return array();
        }

        $like_term = $wpdb->esc_like( $term );
        $prefix    = $like_term . '%';

        // Detect "first last" input.
        $parts = preg_split( '/\s+/', $term );
        $parts = array_values( array_filter( array_map( 'trim', (array) $parts ) ) );

        // Heuristic: treat anything containing '@' as email-ish.
        $is_emailish = false !== strpos( $term, '@' );

        if ( count( $parts ) >= 2 ) {
            $this->last_lookup_debug['mode'] = 'name_pair_prefix';
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

            $ids = $wpdb->get_col( $sql );
        } else {
            $this->last_lookup_debug['mode'] = 'prefix_multi_column';
            // Prefix search across indexed-ish columns.
            $sql = $wpdb->prepare(
                "SELECT user_id
                 FROM {$table}
                 WHERE user_id > 0
                 AND (email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR username LIKE %s)
                 ORDER BY date_registered DESC
                 LIMIT %d",
                $prefix,
                $prefix,
                $prefix,
                $prefix,
                $limit
            );

            $ids = $wpdb->get_col( $sql );

            // If this looks like an email fragment and prefix found nothing, fall back to contains on email.
            // This is still much cheaper than wp_usermeta joins in practice.
            if ( empty( $ids ) && $is_emailish && strlen( $term ) >= 3 ) {
                $this->last_lookup_debug['mode'] = 'contains_email_fallback';
                $contains = '%' . $like_term . '%';
                $sql2 = $wpdb->prepare(
                    "SELECT user_id
                     FROM {$table}
                     WHERE user_id > 0
                     AND email LIKE %s
                     ORDER BY date_registered DESC
                     LIMIT %d",
                    $contains,
                    $limit
                );
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

        try {
            if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) &&
                method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) &&
                \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
                $counts = $this->get_order_counts_hpos( $user_ids );
                return $counts + $order_counts;
            }
        } catch ( Exception $e ) {
            // Fall back to legacy logic on failure.
        }

        $legacy_counts = $this->get_order_counts_legacy_batch( $user_ids );

        return $legacy_counts + $order_counts;
    }

    /**
     * Get total orders for a given customer ID.
     * Optimized to use direct SQL COUNT query instead of loading all order IDs.
     *
     * @param int $user_id Customer user ID.
     *
     * @return int Number of orders for the customer.
     */
    protected function get_order_count_for_customer( $user_id ) {
        global $wpdb;

        // Validate user ID
        if ( empty( $user_id ) || ! is_numeric( $user_id ) ) {
            return 0;
        }

        $user_id = (int) $user_id;

        try {
            // Check if HPOS (High-Performance Order Storage) is enabled (WooCommerce 7.1+)
            if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) &&
                 method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) &&
                 \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {

                // Use HPOS tables for better performance
                $orders_table = $wpdb->prefix . 'wc_orders';

                // Build status list
                $statuses = array_keys( wc_get_order_statuses() );
                if ( empty( $statuses ) ) {
                    return 0;
                }

                $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

                // Prepare and execute COUNT query
                $query = $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$orders_table}
                     WHERE customer_id = %d
                     AND status IN ({$status_placeholders})",
                    array_merge( array( $user_id ), $statuses )
                );

                $count = $wpdb->get_var( $query );
                return $count !== null ? (int) $count : 0;
            }
        } catch ( Exception $e ) {
            // Fall through to legacy method if HPOS check fails
        }

        // Fallback to legacy posts table for older WooCommerce or if HPOS not enabled
        return $this->get_order_count_legacy( $user_id );
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
     * Get recent orders for a given customer ID + email.
     * Returns simplified data suitable for JSON.
     *
     * IMPORTANT: Avoid calling this in a loop for many customers.
     * Doing so reintroduces an N+1 query pattern (one `wc_get_orders()` call per customer).
     * Prefer `get_recent_orders_for_customers()` which batches the fetch.
     *
     * @deprecated Internal helper; use `get_recent_orders_for_customers()` for multi-customer searches.
     *
     * @param int    $user_id
     * @param string $email
     *
     * @return array
     */
    protected function get_recent_orders_for_customer( $user_id, $email ) {
        // Tripwire: if this gets called multiple times in a single request, it likely indicates
        // an accidental N+1 reintroduction. Log a warning (debug default-on) to make it obvious.
        static $call_count = 0;
        $call_count++;
        if ( $call_count === 2 ) {
            $this->debug_log(
                'warning_potential_n_plus_one',
                array(
                    'hint'  => 'get_recent_orders_for_customer() called multiple times; prefer get_recent_orders_for_customers().',
                    'count' => $call_count,
                )
            );
        }

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

        if ( empty( $orders ) && empty( $user_id ) && is_email( $email ) ) {
            $args['customer'] = $email;
            $orders           = wc_get_orders( $args );
        }

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

        // NOTE: Do not rely on `wc_get_orders( [ 'customer' => [ids...] ] )`.
        // Some WooCommerce versions/docs only support a single customer ID/email.
        // Instead, fetch matching order IDs with a direct legacy SQL query (IN list),
        // then hydrate those orders in one go.

        $statuses = array_keys( wc_get_order_statuses() );
        if ( empty( $statuses ) ) {
            return $results;
        }

        // Phase 3 FIX: Cap candidate_limit to prevent memory exhaustion
        // Previous: count($user_ids) * 10 * 5 could be 1000+ orders (100MB+ memory)
        // Fixed: Absolute maximum of 200 orders (~20MB max)
        $candidate_limit = min( count( $user_ids ) * 10 * 5, 200 );

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

        $rows = $wpdb->get_results( $sql );
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

        // Phase 3 OPTIMIZATION: Use direct SQL instead of wc_get_orders()
        // Previous: wc_get_orders() loads full WC_Order objects (~100KB each)
        // Fixed: Direct SQL fetches only needed fields (~1KB each)
        // Memory savings: 200 orders × 99KB = ~20MB saved!
        $this->query_monitor->log_query( 'get_order_summaries', array( 'count' => count( $all_order_ids ) ) );
        $order_summaries = $this->order_formatter->get_order_summaries( $all_order_ids );

        if ( empty( $order_summaries ) ) {
            return $results;
        }

        // Index by order ID for fast lookup
        $orders_by_id = array();
        foreach ( $order_summaries as $order ) {
            $orders_by_id[ (int) $order['id'] ] = $order;
        }

        // Group orders by customer
        foreach ( $order_ids_by_customer as $customer_id => $order_ids ) {
            if ( empty( $order_ids ) ) {
                continue;
            }

            foreach ( $order_ids as $order_id ) {
                if ( ! isset( $orders_by_id[ $order_id ] ) ) {
                    continue;
                }
                $results[ $customer_id ][] = $orders_by_id[ $order_id ];
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

        // `esc_url()` is for HTML output contexts and will entity-encode `&` as `&#038;`.
        // This payload is returned as JSON and inserted via JS; it must be a raw URL.
        $edit_link = get_edit_post_link( $order_id, 'raw' );
        if ( empty( $edit_link ) ) {
            $edit_link = admin_url( 'post.php?post=' . (int) $order_id . '&action=edit' );
        }

        return array(
            'id'            => (int) $order_id,
            'number'        => esc_html( $order->get_order_number() ),
            'status'        => esc_attr( $status ),
            'status_label'  => esc_html( wc_get_order_status_name( $status ) ),
            'total'         => wc_price( $total, array( 'currency' => $currency ) ),
            'date'          => esc_html( $date_created ? $date_created->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '' ),
            'payment'       => esc_html( $payment ),
            'shipping'      => esc_html( $shipping ),
            'view_url'      => esc_url_raw( $edit_link ),
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

<?php
/**
 * AJAX Handler for Customer & Order Search
 *
 * Extracted from main plugin file for better separation of concerns.
 * Handles all AJAX requests for the search functionality.
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.1.9
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_Ajax_Handler {

    /**
     * Register AJAX hooks.
     */
    public function register(): void {
        add_action( 'wp_ajax_kiss_woo_customer_search', array( $this, 'handle_search' ) );
        add_action( 'wp_ajax_kiss_woo_list_wholesale_orders', array( $this, 'handle_list_wholesale_orders' ) );
        add_action( 'wp_ajax_kiss_woo_list_recent_orders', array( $this, 'handle_list_recent_orders' ) );
    }

    /**
     * Handle AJAX request for customer & order search.
     *
     * This method orchestrates the search process:
     * 1. Validates permissions and nonce
     * 2. Sanitizes input
     * 3. Performs customer, guest order, and order number searches
     * 4. Returns structured JSON response
     */
    public function handle_search(): void {
        KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'request_start', array(
            'action' => 'kiss_woo_customer_search',
        ) );

        // Validate permissions.
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'auth_failed', array(
                'user_id' => get_current_user_id(),
            ), 'warn' );
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'kiss-woo-customer-order-search' ) ), 403 );
        }

        // Validate nonce.
        if ( ! check_ajax_referer( 'kiss_woo_cos_search', 'nonce', false ) ) {
            KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'nonce_failed', array(), 'warn' );
            wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'kiss-woo-customer-order-search' ) ), 403 );
        }

        // Sanitize and validate input.
        $term  = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
        $scope = isset( $_POST['scope'] ) ? sanitize_text_field( wp_unslash( $_POST['scope'] ) ) : 'users';

        if ( ! in_array( $scope, array( 'users', 'coupons' ), true ) ) {
            $scope = 'users';
        }

        if ( strlen( $term ) < 2 ) {
            wp_send_json_error( array( 'message' => __( 'Please enter at least 2 characters.', 'kiss-woo-customer-order-search' ) ) );
        }

        KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'search_term', array(
            'term'   => $term,
            'length' => strlen( $term ),
        ) );

        $t_start = microtime( true );

        // Perform search.
        $result = $this->perform_search( $term, $scope );

        // Calculate timing.
        $elapsed_seconds = round( microtime( true ) - $t_start, 2 );
        $elapsed_ms      = round( ( microtime( true ) - $t_start ) * 1000, 2 );

        KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'request_complete', array(
            'elapsed_ms'     => $elapsed_ms,
            'customer_count' => count( $result['customers'] ),
            'guest_count'    => count( $result['guest_orders'] ),
            'order_count'    => count( $result['orders'] ),
            'coupon_count'   => isset( $result['coupons'] ) ? count( $result['coupons'] ) : 0,
        ) );

        // Build response.
        $response = array(
            'customers'                => $result['customers'],
            'guest_orders'             => $result['guest_orders'],
            'orders'                   => $result['orders'],
            'coupons'                  => isset( $result['coupons'] ) ? $result['coupons'] : array(),
            'should_redirect_to_order' => $result['should_redirect_to_order'],
            'redirect_url'             => $result['redirect_url'],
            'search_time'              => $elapsed_seconds,
            'search_time_ms'           => $elapsed_ms,
            'search_scope'             => isset( $result['search_scope'] ) ? $result['search_scope'] : 'users',
        );

        // Add debug data if enabled.
        if ( defined( 'KISS_WOO_FAST_SEARCH_DEBUG' ) && KISS_WOO_FAST_SEARCH_DEBUG ) {
            $response['debug'] = array(
                'traces'         => KISS_Woo_Debug_Tracer::get_traces(),
                'memory_peak_mb' => round( memory_get_peak_usage() / 1024 / 1024, 2 ),
                'php_version'    => PHP_VERSION,
                'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
            );
        }

        wp_send_json_success( $response );
    }

    /**
     * Perform the actual search operations.
     *
     * @param string $term Search term.
     * @return array Search results with customers, guest_orders, orders, and redirect info.
     */
    private function perform_search( string $term, string $scope = 'users' ): array {
        // Initialize search components.
        $search         = new KISS_Woo_COS_Search();
        $cache          = new KISS_Woo_Search_Cache();
        $order_resolver = new KISS_Woo_Order_Resolver( $cache );

        // Get filters from request (SOLID - Open/Closed Principle).
        $filters = $this->get_filters_from_request();

        if ( 'coupons' === $scope ) {
            $coupon_search = new KISS_Woo_Coupon_Search();

            $done    = KISS_Woo_Debug_Tracer::start_timer( 'AjaxHandler', 'coupon_search' );
            $coupons = $coupon_search->search_coupons( $term );
            $done( array( 'count' => count( $coupons ) ) );

            // Auto-redirect if exactly 1 coupon found (same pattern as order search)
            $should_redirect = false;
            $redirect_url    = null;

            if ( 1 === count( $coupons ) ) {
                $should_redirect = true;
                $redirect_url    = $coupons[0]['view_url'];

                KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'coupon_redirect', array(
                    'coupon_id' => $coupons[0]['id'],
                    'code'      => $coupons[0]['code'],
                    'url'       => $redirect_url,
                ) );
            }

            return array(
                'customers'                => array(),
                'guest_orders'             => array(),
                'orders'                   => array(),
                'coupons'                  => $coupons,
                'should_redirect_to_order' => $should_redirect,
                'redirect_url'             => $redirect_url,
                'search_scope'             => 'coupons',
            );
        }

        // Customer search.
        $done             = KISS_Woo_Debug_Tracer::start_timer( 'AjaxHandler', 'customer_search' );
        $customer_results = $search->search_customers( $term, $filters );

        // Handle both flat array (no filters) and structured hash (with filters).
        if ( ! empty( $filters ) && isset( $customer_results['customers'] ) ) {
            // Filters applied - structured response.
            $customers    = $customer_results['customers'];
            $guest_orders = isset( $customer_results['guest_orders'] ) ? $customer_results['guest_orders'] : array();
            $done( array( 'count' => count( $customers ), 'filters' => count( $filters ), 'structure' => 'hash' ) );
        } else {
            // No filters - flat array response.
            $customers    = $customer_results;
            $guest_orders = array();
            $done( array( 'count' => count( $customers ), 'filters' => count( $filters ), 'structure' => 'flat' ) );
        }

        // Guest order search (only if filters didn't already populate it).
        if ( empty( $filters ) ) {
            $done         = KISS_Woo_Debug_Tracer::start_timer( 'AjaxHandler', 'guest_search' );
            $guest_orders = $search->search_guest_orders_by_email( $term );
            $done( array( 'count' => count( $guest_orders ) ) );
        }

        // Order number search (if term looks like an order number).
        $orders                   = array();
        $should_redirect_to_order = false;
        $redirect_url             = null;

        if ( $order_resolver->looks_like_order_number( $term ) ) {
            $done       = KISS_Woo_Debug_Tracer::start_timer( 'AjaxHandler', 'order_search' );
            $resolution = $order_resolver->resolve( $term );

            if ( $resolution['order'] ) {
                $formatted = KISS_Woo_Order_Formatter::format( $resolution['order'] );

                // Legacy alias (one version): older JS/tests used `number`.
                if ( ! isset( $formatted['number'] ) ) {
                    $formatted['number'] = (string) $formatted['order_number'];
                }

                $orders                   = array( $formatted );
                $should_redirect_to_order = true;
                $redirect_url             = $formatted['view_url'];

                $done( array( 'found' => true, 'order_id' => $formatted['id'] ) );
            } else {
                $done( array( 'found' => false, 'source' => $resolution['source'] ) );
            }
        } else {
            KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'order_search_skipped', array(
                'reason' => 'Term does not look like order number',
                'term'   => $term,
            ) );
        }

        return array(
            'customers'                => $customers,
            'guest_orders'             => $guest_orders,
            'orders'                   => $orders,
            'coupons'                  => array(),
            'should_redirect_to_order' => $should_redirect_to_order,
            'redirect_url'             => $redirect_url,
            'search_scope'             => $scope,
        );
    }

    /**
     * Get filters from AJAX request.
     *
     * @return array Array of KISS_Woo_Order_Filter instances.
     */
    private function get_filters_from_request(): array {
        $filters = array();

        // Check for wholesale_only parameter.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_search_request().
        if ( isset( $_POST['wholesale_only'] ) && '1' === $_POST['wholesale_only'] ) {
            $filters[] = new KISS_Woo_Wholesale_Filter();

            KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'filter_applied', array(
                'filter' => 'wholesale',
            ) );
        }

        return $filters;
    }

    /**
     * Handle AJAX request for listing wholesale orders.
     *
     * This endpoint lists ALL wholesale orders with pagination (no search term required).
     * Uses KISS_Woo_Order_Query for centralized, performant order listing.
     *
     * @since 1.2.5
     */
    public function handle_list_wholesale_orders(): void {
        $t_start = microtime( true );

        KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'list_wholesale_start', array(
            'action' => 'kiss_woo_list_wholesale_orders',
        ) );

        // Validate permissions.
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'auth_failed', array(
                'user_id' => get_current_user_id(),
            ), 'warn' );
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'kiss-woo-customer-order-search' ) ), 403 );
        }

        // Validate nonce.
        if ( ! check_ajax_referer( 'kiss_woo_cos_search', 'nonce', false ) ) {
            KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'nonce_failed', array(), 'warn' );
            wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'kiss-woo-customer-order-search' ) ), 403 );
        }

        // Get pagination parameters.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
        $page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
        $per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 100;

        // Cap per_page for safety.
        $per_page = min( 500, max( 1, $per_page ) );

        KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'list_wholesale_params', array(
            'page'     => $page,
            'per_page' => $per_page,
        ) );

        // Use centralized order query helper.
        $query   = new KISS_Woo_Order_Query();
        $results = $query->query_orders( 'wholesale', $page, $per_page );

        $elapsed_ms = round( ( microtime( true ) - $t_start ) * 1000, 2 );

        KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'list_wholesale_complete', array(
            'orders'     => count( $results['orders'] ),
            'total'      => $results['total'],
            'pages'      => $results['pages'],
            'elapsed_ms' => $elapsed_ms,
        ) );

        // Send response.
        wp_send_json_success( array(
            'orders'       => $results['orders'],
            'total'        => $results['total'],
            'pages'        => $results['pages'],
            'current_page' => $results['current_page'],
            'per_page'     => $per_page,
            'elapsed_ms'   => $elapsed_ms,
            'debug'        => $this->get_debug_data(),
        ) );
    }

    /**
     * Handle AJAX request to list recent orders (most recent 50).
     *
     * This endpoint lists the most recent 50 orders (not time-based).
     * Uses KISS_Woo_Order_Query for centralized, performant order listing.
     *
     * @since 1.2.6
     */
    public function handle_list_recent_orders(): void {
        $t_start = microtime( true );

        KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'list_recent_start', array(
            'action' => 'kiss_woo_list_recent_orders',
        ) );

        // Validate permissions.
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'auth_failed', array(
                'user_id' => get_current_user_id(),
            ), 'warn' );
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'kiss-woo-customer-order-search' ) ), 403 );
        }

        // Validate nonce.
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'kiss_woo_cos_search' ) ) {
            KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'nonce_failed', array(), 'warn' );
            wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'kiss-woo-customer-order-search' ) ), 403 );
        }

        // Get pagination parameters.
        $page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
        $per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 50;

        // For recent orders, we always show exactly 50 orders (1 page only).
        $per_page = 50;
        $page     = 1;

        KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'list_recent_params', array(
            'page'     => $page,
            'per_page' => $per_page,
        ) );

        // Use centralized order query helper (no date filter, just ORDER BY date DESC LIMIT 50).
        $query   = new KISS_Woo_Order_Query();
        $results = $query->query_orders( 'all', $page, $per_page, array() );

        $elapsed_ms = round( ( microtime( true ) - $t_start ) * 1000, 2 );

        KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'list_recent_complete', array(
            'orders'     => count( $results['orders'] ),
            'total'      => $results['total'],
            'pages'      => $results['pages'],
            'elapsed_ms' => $elapsed_ms,
        ) );

        // Send response.
        wp_send_json_success( array(
            'orders'       => $results['orders'],
            'total'        => $results['total'],
            'pages'        => $results['pages'],
            'current_page' => $results['current_page'],
            'per_page'     => $per_page,
            'elapsed_ms'   => $elapsed_ms,
            'debug'        => $this->get_debug_data(),
        ) );
    }

    /**
     * Get debug data for response (only if debug enabled).
     *
     * @return array|null
     */
    private function get_debug_data(): ?array {
        if ( ! defined( 'KISS_WOO_FAST_SEARCH_DEBUG' ) || ! KISS_WOO_FAST_SEARCH_DEBUG ) {
            return null;
        }

        return array(
            'traces'         => KISS_Woo_Debug_Tracer::get_traces(),
            'memory_peak_mb' => round( memory_get_peak_usage() / 1024 / 1024, 2 ),
            'php_version'    => PHP_VERSION,
            'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
        );
    }
}

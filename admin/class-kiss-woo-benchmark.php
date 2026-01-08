<?php
if (!defined('ABSPATH')) exit;

class KISS_Woo_COS_Benchmark {

    public static function run_tests($query = 'vishal@neochro.me') {

        $results = [];

        // -------------------------------
        // 1. WooCommerce Order Search Benchmark
        // -------------------------------
        $start = microtime(true);
        $wc_orders = wc_get_orders([
            'search' => '*' . esc_attr($query) . '*',
            'limit'  => 10,
        ]);
        $results['wc_order_search_ms'] = round((microtime(true) - $start) * 1000, 2);

        // -------------------------------
        // 2. WooCommerce Customer (User) Search Benchmark
        // -------------------------------
        $start = microtime(true);
        $wp_user_query = new WP_User_Query([
            'search' => '*' . $query . '*',
            'search_columns' => ['user_email', 'user_login', 'display_name'],
        ]);
        $results['wp_user_search_ms'] = round((microtime(true) - $start) * 1000, 2);

        // -------------------------------
        // 3. KISS Customer Search Benchmark
        // -------------------------------
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-search.php';
        $kiss = new KISS_Woo_COS_Search();

        $start = microtime(true);
        $customers = $kiss->search_customers($query);
        $results['kiss_customer_search_ms'] = round((microtime(true) - $start) * 1000, 2);

        // -------------------------------
        // 4. KISS Guest Order Search
        // -------------------------------
        $start = microtime(true);
        $guest = $kiss->search_guest_orders_by_email($query);
        $results['kiss_guest_search_ms'] = round((microtime(true) - $start) * 1000, 2);

        // -------------------------------
        // 5. WooCommerce Stock Order ID Search (for comparison)
        // -------------------------------
        // Get a real order ID for testing
        $test_orders = wc_get_orders(['limit' => 1]);
        if (!empty($test_orders)) {
            $test_order_id = $test_orders[0]->get_id();
            $test_order_number = $test_orders[0]->get_order_number();

            // WooCommerce stock search by order number (string search)
            $start = microtime(true);
            $wc_stock_search = wc_get_orders([
                'search' => '*' . $test_order_number . '*',
                'limit'  => 10,
            ]);
            $results['wc_stock_order_search_ms'] = round((microtime(true) - $start) * 1000, 2);

            // KISS order search (fast path - direct ID lookup)
            // NOTE: Using numeric ID to test the true fast-path (wc_get_order by ID)
            // If your site uses non-numeric order numbers, KISS will fail-fast (no match)
            $start = microtime(true);
            $kiss_order_search = $kiss->search_orders_by_number((string) $test_order_id);
            $results['kiss_order_search_ms'] = round((microtime(true) - $start) * 1000, 2);

            // Second run (warm - same request, object may be cached by WooCommerce)
            // NOTE: This is NOT plugin-level caching, just same-request object reuse
            $start = microtime(true);
            $kiss_order_search_warm = $kiss->search_orders_by_number((string) $test_order_id);
            $results['kiss_order_search_warm_ms'] = round((microtime(true) - $start) * 1000, 2);

            $results['test_order_id'] = $test_order_id;
            $results['test_order_number'] = $test_order_number;
        } else {
            $results['wc_stock_order_search_ms'] = 'N/A (no orders)';
            $results['kiss_order_search_ms'] = 'N/A (no orders)';
            $results['kiss_order_search_warm_ms'] = 'N/A (no orders)';
        }

        return $results;
    }
}

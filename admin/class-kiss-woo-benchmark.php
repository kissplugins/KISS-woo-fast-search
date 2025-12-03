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

        return $results;
    }
}

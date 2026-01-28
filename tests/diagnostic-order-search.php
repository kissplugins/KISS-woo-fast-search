<?php
/**
 * Diagnostic script for order search debugging.
 *
 * Add to functions.php or run in admin:
 * add_action( 'admin_init', function() {
 *     if ( isset( $_GET['kiss_diag'] ) ) {
 *         include plugin_dir_path( __FILE__ ) . 'tests/diagnostic-order-search.php';
 *         exit;
 *     }
 * });
 *
 * Then visit: /wp-admin/?kiss_diag=1&order=B331580
 */

// This file should be included from WordPress context (admin_init hook).
if ( ! defined( 'ABSPATH' ) ) {
    die( 'Access this via WordPress admin: /wp-admin/?kiss_diag=1' );
}

// Ensure we're admin.
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    die( 'Access denied. You need manage_woocommerce capability.' );
}

header( 'Content-Type: text/plain; charset=utf-8' );

echo "=== KISS Order Search Diagnostic v2 ===\n";
echo "Time: " . current_time( 'Y-m-d H:i:s' ) . "\n\n";

// Test configuration.
$test_order_number = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'B331580';

echo "Test Order Number: {$test_order_number}\n\n";

// Step 1: Check if Sequential Order Numbers Pro is active.
echo "--- Step 1: Check Plugin Status ---\n";
$seq_active = function_exists( 'wc_seq_order_number_pro' );
echo "wc_seq_order_number_pro() exists: " . ( $seq_active ? 'YES' : 'NO' ) . "\n";

// Check for other common sequential plugins.
$other_plugins = array(
    'wc_sequential_order_numbers' => function_exists( 'wc_sequential_order_numbers' ),
    'WC_Seq_Order_Number'         => class_exists( 'WC_Seq_Order_Number' ),
    'WC_Seq_Order_Number_Pro'     => class_exists( 'WC_Seq_Order_Number_Pro' ),
);
foreach ( $other_plugins as $name => $exists ) {
    echo "{$name} exists: " . ( $exists ? 'YES' : 'NO' ) . "\n";
}
echo "\n";

// Step 2: Check HPOS status.
echo "--- Step 2: WooCommerce HPOS Status ---\n";
$hpos_enabled = false;
if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
    $hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    echo "HPOS Enabled: " . ( $hpos_enabled ? 'YES' : 'NO' ) . "\n";
} else {
    echo "OrderUtil class not available (older WooCommerce)\n";
}
echo "\n";

// Step 3: Direct database lookup.
echo "--- Step 3: Direct Database Lookup ---\n";
global $wpdb;

if ( $hpos_enabled ) {
    // HPOS: Search in wc_orders_meta.
    $meta_table = $wpdb->prefix . 'wc_orders_meta';
    $sql = $wpdb->prepare(
        "SELECT order_id, meta_value FROM {$meta_table}
         WHERE meta_key = '_order_number_formatted'
         AND meta_value = %s LIMIT 5",
        $test_order_number
    );
    echo "HPOS Query: {$sql}\n";
    $results = $wpdb->get_results( $sql );
    echo "Results: " . count( $results ) . "\n";
    foreach ( $results as $row ) {
        echo "  Order ID: {$row->order_id}, Value: {$row->meta_value}\n";
    }

    // Also try LIKE search.
    $sql2 = $wpdb->prepare(
        "SELECT order_id, meta_value FROM {$meta_table}
         WHERE meta_key = '_order_number_formatted'
         AND meta_value LIKE %s LIMIT 5",
        '%' . $wpdb->esc_like( $test_order_number ) . '%'
    );
    echo "\nHPOS LIKE Query: {$sql2}\n";
    $results2 = $wpdb->get_results( $sql2 );
    echo "LIKE Results: " . count( $results2 ) . "\n";
    foreach ( $results2 as $row ) {
        echo "  Order ID: {$row->order_id}, Value: {$row->meta_value}\n";
    }

    // Sample some existing values.
    echo "\nSample _order_number_formatted values (HPOS):\n";
    $samples = $wpdb->get_results(
        "SELECT order_id, meta_value FROM {$meta_table}
         WHERE meta_key = '_order_number_formatted'
         ORDER BY order_id DESC LIMIT 5"
    );
    foreach ( $samples as $row ) {
        echo "  Order ID: {$row->order_id}, Value: '{$row->meta_value}'\n";
    }
} else {
    // Legacy: Search in postmeta.
    $sql = $wpdb->prepare(
        "SELECT post_id, meta_value FROM {$wpdb->postmeta}
         WHERE meta_key = '_order_number_formatted'
         AND meta_value = %s LIMIT 5",
        $test_order_number
    );
    echo "Legacy Query: {$sql}\n";
    $results = $wpdb->get_results( $sql );
    echo "Results: " . count( $results ) . "\n";
    foreach ( $results as $row ) {
        echo "  Post ID: {$row->post_id}, Value: {$row->meta_value}\n";
    }

    // Sample some existing values.
    echo "\nSample _order_number_formatted values (postmeta):\n";
    $samples = $wpdb->get_results(
        "SELECT post_id, meta_value FROM {$wpdb->postmeta}
         WHERE meta_key = '_order_number_formatted'
         ORDER BY meta_id DESC LIMIT 5"
    );
    foreach ( $samples as $row ) {
        echo "  Post ID: {$row->post_id}, Value: '{$row->meta_value}'\n";
    }
}
echo "\n";

// Step 4: Test Sequential Plugin lookup directly.
echo "--- Step 4: Sequential Plugin Direct Call ---\n";
if ( $seq_active ) {
    $plugin = wc_seq_order_number_pro();
    echo "Plugin Class: " . get_class( $plugin ) . "\n";

    $found_id = $plugin->find_order_by_order_number( $test_order_number );
    echo "find_order_by_order_number('{$test_order_number}'): " . ( $found_id ? $found_id : 'NOT FOUND (0)' ) . "\n";

    // Try without prefix.
    $number_only = preg_replace( '/^[A-Z]+/i', '', $test_order_number );
    $found_id2   = $plugin->find_order_by_order_number( $number_only );
    echo "find_order_by_order_number('{$number_only}'): " . ( $found_id2 ? $found_id2 : 'NOT FOUND (0)' ) . "\n";
} else {
    echo "Sequential Order Numbers Pro not active - cannot test.\n";
}
echo "\n";

// Step 5: Test our OrderResolver.
echo "--- Step 5: KISS Order Resolver ---\n";
if ( class_exists( 'KISS_Woo_Search_Cache' ) && class_exists( 'KISS_Woo_Order_Resolver' ) ) {
    $cache    = new KISS_Woo_Search_Cache();
    $resolver = new KISS_Woo_Order_Resolver( $cache );

    echo "looks_like_order_number('{$test_order_number}'): " . ( $resolver->looks_like_order_number( $test_order_number ) ? 'YES' : 'NO' ) . "\n";

    $result = $resolver->resolve( $test_order_number );
    echo "resolve('{$test_order_number}'):\n";
    echo "  Order Found: " . ( $result['order'] ? 'YES (ID: ' . $result['order']->get_id() . ')' : 'NO' ) . "\n";
    echo "  Source: " . $result['source'] . "\n";
    echo "  Cached: " . ( $result['cached'] ? 'YES' : 'NO' ) . "\n";
} else {
    echo "KISS classes not loaded.\n";
}

echo "\n=== Diagnostic Complete ===\n";


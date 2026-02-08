<?php
/**
 * Test KISS_Woo_Order_Query directly
 * Run with: wp eval-file test-query-direct.php --skip-plugins --skip-themes
 */

global $wpdb;

// Load required classes
require_once '/Users/noelsaw/Local Sites/1-bloomzhemp-production-sync-07-24/app/public/wp-content/plugins/KISS-woo-fast-search/includes/class-kiss-woo-utils.php';
require_once '/Users/noelsaw/Local Sites/1-bloomzhemp-production-sync-07-24/app/public/wp-content/plugins/KISS-woo-fast-search/includes/class-kiss-woo-debug-tracer.php';
require_once '/Users/noelsaw/Local Sites/1-bloomzhemp-production-sync-07-24/app/public/wp-content/plugins/KISS-woo-fast-search/includes/class-kiss-woo-order-formatter.php';
require_once '/Users/noelsaw/Local Sites/1-bloomzhemp-production-sync-07-24/app/public/wp-content/plugins/KISS-woo-fast-search/includes/class-kiss-woo-order-query.php';

echo "\n=== TESTING KISS_Woo_Order_Query DIRECTLY ===\n\n";

// Check HPOS status
$is_hpos = KISS_Woo_Utils::is_hpos_enabled();
echo "HPOS enabled: " . ( $is_hpos ? 'YES' : 'NO (Legacy mode)' ) . "\n\n";

// Create query instance
$query = new KISS_Woo_Order_Query();

// Test wholesale query
echo "=== QUERYING WHOLESALE ORDERS ===\n\n";
$results = $query->query_orders( 'wholesale', 1, 100 );

echo "Total orders: {$results['total']}\n";
echo "Pages: {$results['pages']}\n";
echo "Current page: {$results['current_page']}\n";
echo "Query time: {$results['elapsed_ms']}ms\n\n";

if ( empty( $results['orders'] ) ) {
    echo "❌ No orders returned!\n\n";
    
    // Debug: Run raw SQL
    echo "=== DEBUGGING: Running raw SQL ===\n\n";
    
    $count_sql = "SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'shop_order'
        AND pm.meta_key = '_wwpp_order_type'
        AND pm.meta_value = 'wholesale'";
    
    $count = $wpdb->get_var( $count_sql );
    echo "Raw SQL count: {$count}\n";
    
    if ( $count > 0 ) {
        echo "❌ Raw SQL found {$count} orders, but query_orders() returned 0!\n";
        echo "This indicates a bug in the query builder.\n";
    }
    
    exit( 1 );
}

echo "Orders returned: " . count( $results['orders'] ) . "\n\n";

// Inspect first 3 orders
echo "=== ORDER DETAILS ===\n\n";

foreach ( array_slice( $results['orders'], 0, 3 ) as $i => $order ) {
    echo "--- Order " . ( $i + 1 ) . " ---\n";
    echo "ID: {$order['id']}\n";
    echo "Status: {$order['status']}\n";
    echo "Date: {$order['date_created']}\n";
    echo "Total: " . ( $order['total_amount'] ?? 'MISSING' ) . " " . ( $order['currency'] ?? 'MISSING' ) . "\n";
    echo "Customer: " . ( $order['first_name'] ?? 'MISSING' ) . " " . ( $order['last_name'] ?? 'MISSING' ) . "\n";
    echo "Email: " . ( $order['billing_email'] ?? 'MISSING' ) . "\n";
    
    // Check for missing/empty fields
    $required_fields = array( 'total_amount', 'currency', 'billing_email', 'first_name', 'last_name' );
    $missing = array();
    $empty = array();
    
    foreach ( $required_fields as $field ) {
        if ( ! isset( $order[ $field ] ) ) {
            $missing[] = $field;
        } elseif ( empty( $order[ $field ] ) && '0' !== $order[ $field ] ) {
            $empty[] = $field;
        }
    }
    
    if ( ! empty( $missing ) ) {
        echo "❌ Missing fields: " . implode( ', ', $missing ) . "\n";
    }
    if ( ! empty( $empty ) ) {
        echo "⚠️  Empty fields: " . implode( ', ', $empty ) . "\n";
    }
    if ( empty( $missing ) && empty( $empty ) ) {
        echo "✅ All fields present\n";
    }
    
    echo "\n";
}

// If fields are missing, debug the SQL query
if ( ! empty( $missing ) || ! empty( $empty ) ) {
    echo "=== DEBUGGING: Checking raw SQL data ===\n\n";
    
    $first_order_id = $results['orders'][0]['id'];
    
    echo "Querying order #{$first_order_id} meta directly:\n\n";
    
    $meta_keys = array( '_order_total', '_order_currency', '_billing_email', '_billing_first_name', '_billing_last_name' );
    
    foreach ( $meta_keys as $key ) {
        $value = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
            $first_order_id,
            $key
        ) );
        
        echo "{$key}: " . ( $value ? $value : 'NULL' ) . "\n";
    }
}

echo "\n=== TEST COMPLETE ===\n\n";


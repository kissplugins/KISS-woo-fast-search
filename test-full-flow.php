<?php
/**
 * Test the full wholesale query flow
 * Run with: wp eval-file test-full-flow.php
 */

// Load required classes
require_once __DIR__ . '/includes/class-kiss-woo-utils.php';
require_once __DIR__ . '/includes/class-kiss-woo-debug-tracer.php';
require_once __DIR__ . '/includes/class-kiss-woo-order-formatter.php';
require_once __DIR__ . '/includes/class-kiss-woo-order-query.php';

echo "\n=== TESTING FULL WHOLESALE QUERY FLOW ===\n\n";

// Create query instance
$query = new KISS_Woo_Order_Query();

// Query wholesale orders
$results = $query->query_orders( 'wholesale', 1, 100 );

echo "Total orders: {$results['total']}\n";
echo "Orders returned: " . count( $results['orders'] ) . "\n";
echo "Query time: {$results['elapsed_ms']}ms\n\n";

if ( empty( $results['orders'] ) ) {
    echo "❌ No orders returned!\n";
    exit( 1 );
}

// Check first 3 orders
foreach ( array_slice( $results['orders'], 0, 3 ) as $i => $order ) {
    echo "--- Order " . ( $i + 1 ) . " ---\n";
    echo "ID: {$order['id']}\n";
    echo "Status: {$order['status']} ({$order['status_label']})\n";
    echo "Total: {$order['total_display']}\n";
    echo "Customer: {$order['customer']['name']}\n";
    echo "Email: {$order['customer']['email']}\n";
    echo "Date: {$order['date_display']}\n";
    
    // Check for empty fields
    $issues = array();
    if ( empty( $order['total_display'] ) ) $issues[] = 'total_display';
    if ( empty( $order['customer']['name'] ) ) $issues[] = 'customer.name';
    if ( empty( $order['customer']['email'] ) ) $issues[] = 'customer.email';
    
    if ( ! empty( $issues ) ) {
        echo "⚠️  Empty fields: " . implode( ', ', $issues ) . "\n";
        echo "Raw order data:\n";
        print_r( $order );
    } else {
        echo "✅ All fields populated\n";
    }
    
    echo "\n";
}

echo "=== TEST COMPLETE ===\n\n";


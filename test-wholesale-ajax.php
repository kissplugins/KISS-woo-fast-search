<?php
/**
 * Test wholesale AJAX endpoint directly
 * Run with: wp eval-file test-wholesale-ajax.php
 */

// Simulate admin user
wp_set_current_user( 1 );

// Load all required classes
require_once __DIR__ . '/includes/class-kiss-woo-utils.php';
require_once __DIR__ . '/includes/class-kiss-woo-debug-tracer.php';
require_once __DIR__ . '/includes/class-kiss-woo-order-formatter.php';
require_once __DIR__ . '/includes/class-kiss-woo-order-query.php';
require_once __DIR__ . '/includes/class-kiss-woo-ajax-handler.php';

echo "\n=== TESTING WHOLESALE AJAX ENDPOINT ===\n\n";

// Simulate AJAX request
$_POST['action'] = 'kiss_woo_list_wholesale_orders';
$_POST['nonce'] = wp_create_nonce( 'kiss_woo_cos_search' );
$_POST['page'] = 1;
$_POST['per_page'] = 100;

// Create handler instance
$handler = new KISS_Woo_Ajax_Handler();

// Capture output
ob_start();
$handler->handle_list_wholesale_orders();
$output = ob_get_clean();

echo "AJAX Response:\n";
echo $output . "\n\n";

// Parse JSON
$response = json_decode( $output, true );

if ( ! $response ) {
    echo "❌ Failed to parse JSON response\n";
    echo "Raw output: " . substr( $output, 0, 500 ) . "\n";
    exit( 1 );
}

if ( ! $response['success'] ) {
    echo "❌ AJAX request failed\n";
    echo "Error: " . ( $response['data']['message'] ?? 'Unknown error' ) . "\n";
    exit( 1 );
}

$data = $response['data'];

echo "✅ AJAX request successful\n\n";
echo "Total orders: {$data['total']}\n";
echo "Pages: {$data['pages']}\n";
echo "Current page: {$data['current_page']}\n";
echo "Query time: {$data['elapsed_ms']}ms\n\n";

if ( empty( $data['orders'] ) ) {
    echo "❌ No orders returned!\n";
    exit( 1 );
}

echo "Orders returned: " . count( $data['orders'] ) . "\n\n";

// Inspect first order
echo "=== FIRST ORDER DETAILS ===\n\n";
$first_order = $data['orders'][0];

echo "Order structure:\n";
print_r( $first_order );
echo "\n";

// Check for missing fields
$required_fields = array( 'id', 'total_amount', 'currency', 'billing_email', 'first_name', 'last_name' );
$missing_fields = array();
$empty_fields = array();

foreach ( $required_fields as $field ) {
    if ( ! isset( $first_order[ $field ] ) ) {
        $missing_fields[] = $field;
    } elseif ( empty( $first_order[ $field ] ) && '0' !== $first_order[ $field ] ) {
        $empty_fields[] = $field;
    }
}

if ( ! empty( $missing_fields ) ) {
    echo "❌ Missing fields: " . implode( ', ', $missing_fields ) . "\n";
}

if ( ! empty( $empty_fields ) ) {
    echo "⚠️  Empty fields: " . implode( ', ', $empty_fields ) . "\n";
}

if ( empty( $missing_fields ) && empty( $empty_fields ) ) {
    echo "✅ All required fields present and non-empty\n";
}

echo "\n=== FIELD VALUES ===\n\n";
echo "ID: {$first_order['id']}\n";
echo "Total: {$first_order['total_amount']} {$first_order['currency']}\n";
echo "Customer: {$first_order['first_name']} {$first_order['last_name']}\n";
echo "Email: {$first_order['billing_email']}\n";
echo "Status: {$first_order['status']}\n";
echo "Date: {$first_order['date_created']}\n";

echo "\n=== TEST COMPLETE ===\n\n";


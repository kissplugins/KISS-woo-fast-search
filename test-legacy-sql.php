<?php
/**
 * Test legacy SQL query to see what data is returned
 * Run with: wp eval-file test-legacy-sql.php
 */

global $wpdb;

echo "\n=== TESTING LEGACY SQL QUERY ===\n\n";

// This is the EXACT SQL from build_legacy_query() after our fix
$sql = "SELECT 
    p.ID as id,
    p.post_status as status,
    p.post_date_gmt as date_created_gmt,
    MAX(CASE WHEN pm_total.meta_key = '_order_total' THEN pm_total.meta_value END) as total_amount,
    MAX(CASE WHEN pm_currency.meta_key = '_order_currency' THEN pm_currency.meta_value END) as currency,
    MAX(CASE WHEN pm_email.meta_key = '_billing_email' THEN pm_email.meta_value END) as billing_email,
    MAX(CASE WHEN pm_fname.meta_key = '_billing_first_name' THEN pm_fname.meta_value END) as first_name,
    MAX(CASE WHEN pm_lname.meta_key = '_billing_last_name' THEN pm_lname.meta_value END) as last_name
FROM {$wpdb->posts} p
LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
LEFT JOIN {$wpdb->postmeta} pm_currency ON p.ID = pm_currency.post_id AND pm_currency.meta_key = '_order_currency'
LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
LEFT JOIN {$wpdb->postmeta} pm_fname ON p.ID = pm_fname.post_id AND pm_fname.meta_key = '_billing_first_name'
LEFT JOIN {$wpdb->postmeta} pm_lname ON p.ID = pm_lname.post_id AND pm_lname.meta_key = '_billing_last_name'
WHERE p.post_type = 'shop_order'
AND (
    EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key = '_wwpp_order_type' AND m.meta_value IN ('wholesale', 'yes', '1'))
)
GROUP BY p.ID, p.post_status, p.post_date_gmt
ORDER BY p.post_date_gmt DESC
LIMIT 3";

echo "Running SQL query...\n\n";

$results = $wpdb->get_results( $sql, ARRAY_A );

if ( $wpdb->last_error ) {
    echo "❌ SQL Error: {$wpdb->last_error}\n\n";
    exit( 1 );
}

if ( empty( $results ) ) {
    echo "❌ No results returned!\n\n";
    exit( 1 );
}

echo "✅ Found " . count( $results ) . " orders\n\n";

// Inspect each order
foreach ( $results as $i => $order ) {
    echo "--- Order " . ( $i + 1 ) . " ---\n";
    echo "ID: {$order['id']}\n";
    echo "Status: {$order['status']}\n";
    echo "Date: {$order['date_created_gmt']}\n";
    echo "Total: " . ( $order['total_amount'] ?? 'NULL' ) . "\n";
    echo "Currency: " . ( $order['currency'] ?? 'NULL' ) . "\n";
    echo "Email: " . ( $order['billing_email'] ?? 'NULL' ) . "\n";
    echo "First Name: " . ( $order['first_name'] ?? 'NULL' ) . "\n";
    echo "Last Name: " . ( $order['last_name'] ?? 'NULL' ) . "\n";
    
    // Check for NULL/empty values
    $issues = array();
    if ( empty( $order['total_amount'] ) ) $issues[] = 'total_amount';
    if ( empty( $order['currency'] ) ) $issues[] = 'currency';
    if ( empty( $order['billing_email'] ) ) $issues[] = 'billing_email';
    if ( empty( $order['first_name'] ) ) $issues[] = 'first_name';
    if ( empty( $order['last_name'] ) ) $issues[] = 'last_name';
    
    if ( ! empty( $issues ) ) {
        echo "⚠️  Empty/NULL fields: " . implode( ', ', $issues ) . "\n";
    } else {
        echo "✅ All fields populated\n";
    }
    
    echo "\n";
}

// If we have issues, check the meta directly
if ( ! empty( $issues ) ) {
    $first_id = $results[0]['id'];
    echo "=== DEBUGGING: Checking meta for order #{$first_id} ===\n\n";
    
    $meta = $wpdb->get_results( $wpdb->prepare(
        "SELECT meta_key, meta_value FROM {$wpdb->postmeta} 
        WHERE post_id = %d 
        AND meta_key IN ('_order_total', '_order_currency', '_billing_email', '_billing_first_name', '_billing_last_name')
        ORDER BY meta_key",
        $first_id
    ), ARRAY_A );
    
    foreach ( $meta as $row ) {
        echo "{$row['meta_key']}: {$row['meta_value']}\n";
    }
}

echo "\n=== TEST COMPLETE ===\n\n";


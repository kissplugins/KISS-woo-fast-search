<?php
/**
 * Diagnostic script to verify wholesale order count.
 * Run with: wp eval-file diagnostic-wholesale-count.php
 */

global $wpdb;

echo "\n=== WHOLESALE ORDER COUNT DIAGNOSTIC ===\n\n";

// 1. Total orders
$total_orders = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'"
);
echo "1. Total orders in wp_posts: {$total_orders}\n\n";

// 2. Orders with wholesale meta keys (grouped)
echo "2. Orders with wholesale meta keys:\n";
$meta_breakdown = $wpdb->get_results(
    "SELECT 
        pm.meta_key,
        pm.meta_value,
        COUNT(DISTINCT p.ID) as order_count
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'shop_order'
    AND pm.meta_key IN ('_wwpp_order_type', '_wholesale_order', '_is_wholesale_order', '_wwp_wholesale_order')
    GROUP BY pm.meta_key, pm.meta_value
    ORDER BY pm.meta_key, pm.meta_value"
);

if ( empty( $meta_breakdown ) ) {
    echo "   No wholesale meta keys found!\n";
} else {
    foreach ( $meta_breakdown as $row ) {
        echo "   {$row->meta_key} = '{$row->meta_value}': {$row->order_count} orders\n";
    }
}
echo "\n";

// 3. Count orders matching wholesale criteria
$wholesale_count = $wpdb->get_var(
    "SELECT COUNT(DISTINCT p.ID)
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'shop_order'
    AND pm.meta_key IN ('_wwpp_order_type', '_wholesale_order', '_is_wholesale_order', '_wwp_wholesale_order')
    AND pm.meta_value IN ('wholesale', 'yes', '1')"
);
echo "3. Wholesale orders matching criteria: {$wholesale_count}\n\n";

// 4. Sample wholesale orders
echo "4. Sample wholesale orders (first 10):\n";
$sample_orders = $wpdb->get_results(
    "SELECT 
        p.ID,
        p.post_status,
        p.post_date,
        pm.meta_key,
        pm.meta_value
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'shop_order'
    AND pm.meta_key IN ('_wwpp_order_type', '_wholesale_order', '_is_wholesale_order', '_wwp_wholesale_order')
    AND pm.meta_value IN ('wholesale', 'yes', '1')
    ORDER BY p.post_date DESC
    LIMIT 10"
);

if ( empty( $sample_orders ) ) {
    echo "   No wholesale orders found!\n";
} else {
    foreach ( $sample_orders as $order ) {
        echo "   Order #{$order->ID} ({$order->post_status}) - {$order->post_date} - {$order->meta_key}={$order->meta_value}\n";
    }
}
echo "\n";

// 5. Check for wholesale user roles
echo "5. Orders by wholesale users:\n";
$wholesale_user_orders = $wpdb->get_var(
    "SELECT COUNT(DISTINCT p.ID)
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
    INNER JOIN {$wpdb->usermeta} um ON pm_customer.meta_value = um.user_id
    WHERE p.post_type = 'shop_order'
    AND um.meta_key = '{$wpdb->prefix}capabilities'
    AND (
        um.meta_value LIKE '%wholesale_customer%'
        OR um.meta_value LIKE '%wholesale_lead%'
        OR um.meta_value LIKE '%wwpp_wholesale_customer%'
        OR um.meta_value LIKE '%wws_wholesale_customer%'
    )"
);
echo "   Orders by users with wholesale roles: {$wholesale_user_orders}\n\n";

// 6. Check what our query helper returns
echo "6. Testing KISS_Woo_Order_Query:\n";
if ( class_exists( 'KISS_Woo_Order_Query' ) ) {
    $query = new KISS_Woo_Order_Query();
    $results = $query->query_orders( 'wholesale', 1, 100 );
    echo "   Found: {$results['total']} wholesale orders\n";
    echo "   Query time: {$results['elapsed_ms']}ms\n";
    echo "   Pages: {$results['pages']}\n";
    
    if ( ! empty( $results['orders'] ) ) {
        echo "   Sample order IDs: ";
        $sample_ids = array_slice( array_column( $results['orders'], 'id' ), 0, 5 );
        echo implode( ', ', $sample_ids ) . "\n";
    }
} else {
    echo "   KISS_Woo_Order_Query class not found!\n";
}

echo "\n=== END DIAGNOSTIC ===\n\n";


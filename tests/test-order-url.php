<?php
/**
 * Quick diagnostic to test order URL generation.
 * 
 * Access via: /wp-admin/?kiss_test_url=1&order_id=12345
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get order ID from URL parameter
$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

if ( ! $order_id ) {
    echo '<h1>Order URL Test</h1>';
    echo '<p>Usage: Add &order_id=12345 to the URL</p>';
    exit;
}

echo '<h1>Order URL Generation Test</h1>';
echo '<p>Testing Order ID: ' . $order_id . '</p>';
echo '<hr>';

// Test 1: get_edit_post_link
echo '<h2>Test 1: get_edit_post_link()</h2>';
$url1 = get_edit_post_link( $order_id, 'raw' );
echo '<p>Result: ' . ( $url1 ? esc_html( $url1 ) : '<em>NULL/Empty</em>' ) . '</p>';

// Test 2: admin_url with post.php
echo '<h2>Test 2: admin_url( post.php )</h2>';
$url2 = admin_url( 'post.php?post=' . $order_id . '&action=edit' );
echo '<p>Result: ' . esc_html( $url2 ) . '</p>';

// Test 3: WooCommerce get_edit_order_url (if available)
echo '<h2>Test 3: WC Order get_edit_order_url()</h2>';
$order = wc_get_order( $order_id );
if ( $order && method_exists( $order, 'get_edit_order_url' ) ) {
    $url3 = $order->get_edit_order_url();
    echo '<p>Result: ' . esc_html( $url3 ) . '</p>';
} else {
    echo '<p><em>Method not available or order not found</em></p>';
}

// Test 4: Check HPOS status
echo '<h2>Test 4: HPOS Status</h2>';
if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
    $hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    echo '<p>HPOS Enabled: ' . ( $hpos_enabled ? '<strong>YES</strong>' : 'NO' ) . '</p>';
} else {
    echo '<p>OrderUtil class not available</p>';
}

// Test 5: Check order type
echo '<h2>Test 5: Order Type</h2>';
if ( $order ) {
    $post_type = get_post_type( $order_id );
    echo '<p>Post Type: ' . ( $post_type ? esc_html( $post_type ) : '<em>Not a post</em>' ) . '</p>';
    echo '<p>Order Class: ' . esc_html( get_class( $order ) ) . '</p>';
}

echo '<hr>';
echo '<h2>Recommended URL</h2>';
if ( $order && method_exists( $order, 'get_edit_order_url' ) ) {
    echo '<p><strong>Use: $order->get_edit_order_url()</strong></p>';
    echo '<p><a href="' . esc_url( $order->get_edit_order_url() ) . '" target="_blank">Click to test</a></p>';
} else {
    echo '<p><strong>Use: admin_url( post.php )</strong></p>';
    echo '<p><a href="' . esc_url( $url2 ) . '" target="_blank">Click to test</a></p>';
}

